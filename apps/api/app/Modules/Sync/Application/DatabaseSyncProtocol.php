<?php

declare(strict_types=1);

namespace App\Modules\Sync\Application;

use App\Modules\Sync\Application\Contracts\SyncCommandHandler;
use App\Modules\Sync\Application\Contracts\SyncProtocol;
use App\Modules\Sync\Application\Data\SyncChange;
use App\Modules\Sync\Application\Data\SyncChangePage;
use App\Modules\Sync\Application\Data\SyncCommandEnvelope;
use App\Modules\Sync\Application\Data\SyncCommandOutcome;
use App\Modules\Sync\Application\Data\SyncCommandReceipt;
use App\Modules\Tenancy\Application\TenantContext;
use DateTimeImmutable;
use DomainException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use InvalidArgumentException;
use RuntimeException;

final readonly class DatabaseSyncProtocol implements SyncProtocol
{
    public function __construct(private TenantContext $tenants, private SyncCommandHandler $handler) {}

    public function publishChange(string $entityType, string $entityId, string $operation, array $payload): SyncChange
    {
        $tenantId = (string) $this->tenants->id();
        $entityType = trim($entityType);
        $entityId = trim($entityId);
        $operation = strtolower(trim($operation));
        if ($entityType === '' || strlen($entityType) > 128 || $entityId === '' || strlen($entityId) > 128 || ! in_array($operation, ['upsert', 'delete'], true)) {
            throw new InvalidArgumentException('A bounded entity identity and upsert/delete operation are required.');
        }
        $occurredAt = now();
        $cursor = DB::table('sync_changes')->insertGetId([
            'tenant_id' => $tenantId, 'entity_type' => $entityType, 'entity_id' => $entityId,
            'operation' => $operation, 'payload' => json_encode($payload, JSON_THROW_ON_ERROR), 'occurred_at' => $occurredAt,
        ], 'cursor');

        return new SyncChange((int) $cursor, $entityType, $entityId, $operation, $payload, $occurredAt->toISOString());
    }

    public function pull(string $devicePublicId, int $afterCursor, int $limit = 100): SyncChangePage
    {
        $tenantId = (string) $this->tenants->id();
        if ($afterCursor < 0 || $limit < 1 || $limit > 500) {
            throw new InvalidArgumentException('Cursor and page limit are outside the v1 protocol bounds.');
        }
        $deviceId = $this->activeDeviceId($tenantId, $devicePublicId);

        return DB::transaction(function () use ($tenantId, $deviceId, $afterCursor, $limit): SyncChangePage {
            $this->lock("sync-pull:{$tenantId}:{$deviceId}");
            $cursorRow = DB::table('sync_device_cursors')->where(['tenant_id' => $tenantId, 'device_id' => $deviceId])->lockForUpdate()->first();
            $knownCursor = (int) ($cursorRow->last_pulled_cursor ?? 0);
            if ($afterCursor < $knownCursor) {
                throw new DomainException('The supplied cursor regresses behind this device cursor.');
            }
            $rows = DB::table('sync_changes')->where('tenant_id', $tenantId)->where('cursor', '>', $afterCursor)->orderBy('cursor')->limit($limit + 1)->get();
            $hasMore = $rows->count() > $limit;
            $pageRows = $rows->take($limit);
            $pageCursor = $pageRows->isEmpty() ? $afterCursor : (int) $pageRows->last()->cursor;
            DB::table('sync_device_cursors')->updateOrInsert(
                ['tenant_id' => $tenantId, 'device_id' => $deviceId],
                ['last_pulled_cursor' => $pageCursor, 'updated_at' => now()],
            );
            $changes = $pageRows->map(fn (object $row): SyncChange => new SyncChange(
                (int) $row->cursor, (string) $row->entity_type, (string) $row->entity_id, (string) $row->operation,
                (array) json_decode((string) $row->payload, true, flags: JSON_THROW_ON_ERROR),
                (new DateTimeImmutable((string) $row->occurred_at))->format(DATE_ATOM),
            ))->values()->all();

            return new SyncChangePage('1', $pageCursor, $changes, $hasMore);
        });
    }

    public function submit(string $devicePublicId, SyncCommandEnvelope $command): SyncCommandReceipt
    {
        $tenantId = (string) $this->tenants->id();
        $deviceId = $this->activeDeviceId($tenantId, $devicePublicId);
        $this->validate($command);
        $fingerprint = $this->fingerprint($command);

        return DB::transaction(function () use ($tenantId, $deviceId, $command, $fingerprint): SyncCommandReceipt {
            $this->lock("sync-command:{$tenantId}:{$deviceId}:{$command->commandId}");
            $existing = DB::table('sync_command_inbox')->where(['tenant_id' => $tenantId, 'device_id' => $deviceId, 'command_id' => $command->commandId])->lockForUpdate()->first();
            if ($existing !== null) {
                if (! hash_equals((string) $existing->payload_fingerprint, $fingerprint)) {
                    throw new RuntimeException('The command ID is already bound to a different immutable envelope.');
                }

                return $this->receipt($existing);
            }
            $id = (string) Str::uuid();
            $now = now();
            DB::table('sync_command_inbox')->insert([
                'id' => $id, 'tenant_id' => $tenantId, 'device_id' => $deviceId, 'command_id' => $command->commandId,
                'protocol_version' => $command->version, 'command_type' => $command->type, 'client_occurred_at' => $command->occurredAt,
                'payload' => json_encode($command->payload, JSON_THROW_ON_ERROR), 'payload_fingerprint' => $fingerprint,
                'status' => 'received', 'attempt_count' => 0, 'created_at' => $now, 'updated_at' => $now,
            ]);

            return $this->attempt($tenantId, $deviceId, $id, $command);
        });
    }

    public function retry(string $devicePublicId, string $commandId): SyncCommandReceipt
    {
        $tenantId = (string) $this->tenants->id();
        $deviceId = $this->activeDeviceId($tenantId, $devicePublicId);

        return DB::transaction(function () use ($tenantId, $deviceId, $commandId): SyncCommandReceipt {
            $this->lock("sync-command:{$tenantId}:{$deviceId}:{$commandId}");
            $row = DB::table('sync_command_inbox')->where(['tenant_id' => $tenantId, 'device_id' => $deviceId, 'command_id' => $commandId])->lockForUpdate()->first();
            if ($row === null) {
                throw new DomainException('The device command does not exist.');
            }
            if ((string) $row->status !== 'retry_pending') {
                return $this->receipt($row);
            }
            $command = new SyncCommandEnvelope((string) $row->protocol_version, (string) $row->command_id, (string) $row->command_type, (string) $row->client_occurred_at, (array) json_decode((string) $row->payload, true, flags: JSON_THROW_ON_ERROR));

            return $this->attempt($tenantId, $deviceId, (string) $row->id, $command);
        });
    }

    private function attempt(string $tenantId, string $deviceId, string $id, SyncCommandEnvelope $command): SyncCommandReceipt
    {
        $outcome = $this->handler->handle($tenantId, $deviceId, $command);
        $this->assertOutcome($outcome);
        $now = now();
        DB::table('sync_command_inbox')->where(['tenant_id' => $tenantId, 'device_id' => $deviceId, 'id' => $id])->update([
            'status' => $outcome->status, 'attempt_count' => DB::raw('attempt_count + 1'),
            'result_code' => $outcome->code, 'result_message' => $outcome->message, 'last_attempted_at' => $now,
            'completed_at' => in_array($outcome->status, ['applied', 'rejected', 'conflict'], true) ? $now : null, 'updated_at' => $now,
        ]);
        if ($outcome->status === 'conflict') {
            DB::table('sync_conflicts')->insert([
                'id' => (string) Str::uuid(), 'tenant_id' => $tenantId, 'device_id' => $deviceId, 'command_inbox_id' => $id,
                'conflict_code' => $outcome->code, 'message' => $outcome->message,
                'evidence' => json_encode($outcome->evidence, JSON_THROW_ON_ERROR), 'detected_at' => $now,
            ]);
        }

        return $this->receipt(DB::table('sync_command_inbox')->where('id', $id)->firstOrFail());
    }

    private function activeDeviceId(string $tenantId, string $publicId): string
    {
        $id = DB::table('devices')->where('tenant_id', $tenantId)->where('public_id', trim($publicId))->where('status', 'active')->value('id');
        if (! is_string($id)) {
            throw new DomainException('An active tenant device is required.');
        }

        return $id;
    }

    private function validate(SyncCommandEnvelope $command): void
    {
        if ($command->version !== '1' || ! Str::isUuid($command->commandId) || trim($command->type) === '' || strlen($command->type) > 128) {
            throw new InvalidArgumentException('A v1 command UUID and bounded type are required.');
        }
        try {
            new DateTimeImmutable($command->occurredAt);
        } catch (\Throwable) {
            throw new InvalidArgumentException('occurredAt must be an ISO-8601 timestamp.');
        }
    }

    private function fingerprint(SyncCommandEnvelope $command): string
    {
        $value = ['version' => $command->version, 'commandId' => $command->commandId, 'type' => $command->type, 'occurredAt' => $command->occurredAt, 'payload' => $this->canonicalize($command->payload)];

        return hash('sha256', json_encode($value, JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES | JSON_PRESERVE_ZERO_FRACTION));
    }

    private function canonicalize(mixed $value): mixed
    {
        if (! is_array($value)) {
            return $value;
        }
        if (! array_is_list($value)) {
            ksort($value);
        }
        foreach ($value as $key => $item) {
            $value[$key] = $this->canonicalize($item);
        }

        return $value;
    }

    private function assertOutcome(SyncCommandOutcome $outcome): void
    {
        if (! in_array($outcome->status, ['applied', 'rejected', 'conflict', 'retry_pending'], true)) {
            throw new RuntimeException('The command handler returned an invalid status.');
        }
        if ($outcome->status !== 'applied' && (trim((string) $outcome->code) === '' || trim((string) $outcome->message) === '')) {
            throw new RuntimeException('Non-applied outcomes require a code and message.');
        }
        if ($outcome->status === 'conflict' && $outcome->evidence === null) {
            throw new RuntimeException('Conflict outcomes require evidence.');
        }
    }

    private function receipt(object $row): SyncCommandReceipt
    {
        return new SyncCommandReceipt((string) $row->command_id, (string) $row->status, (int) $row->attempt_count, $row->result_code ? (string) $row->result_code : null, $row->result_message ? (string) $row->result_message : null);
    }

    private function lock(string $identity): void
    {
        if (DB::getDriverName() === 'pgsql') {
            DB::select('SELECT pg_advisory_xact_lock(hashtextextended(?, 0))', [$identity]);
        }
    }
}
