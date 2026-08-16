<?php

declare(strict_types=1);

namespace Tests\Feature\Modules\Sync;

use App\Modules\Register\Application\Contracts\RegisterDeviceManager;
use App\Modules\Sync\Application\Contracts\SyncCommandHandler;
use App\Modules\Sync\Application\Contracts\SyncProtocol;
use App\Modules\Sync\Application\Data\SyncCommandEnvelope;
use App\Modules\Sync\Application\Data\SyncCommandOutcome;
use App\Modules\Sync\SyncServiceProvider;
use App\Modules\Tenancy\Application\TenantScope;
use App\Modules\Tenancy\Domain\Branch;
use App\Modules\Tenancy\Domain\Company;
use App\Modules\Tenancy\Domain\Tenant;
use App\Modules\Tenancy\Domain\TenantId;
use DomainException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use PHPUnit\Framework\Attributes\Test;
use RuntimeException;
use Tests\TestCase;

final class SyncProtocolTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->app->register(SyncServiceProvider::class);
    }

    #[Test]
    public function changes_are_tenant_scoped_monotonic_and_device_cursors_cannot_regress(): void
    {
        [$tenantA, $deviceA] = $this->deviceFixture('Changes A');
        [$tenantB, $deviceB] = $this->deviceFixture('Changes B');
        $first = $this->inTenant($tenantA, fn (SyncProtocol $sync) => $sync->publishChange('catalogue.variant', 'A-1', 'upsert', ['name' => 'Tea']));
        $this->inTenant($tenantB, fn (SyncProtocol $sync) => $sync->publishChange('catalogue.variant', 'B-1', 'upsert', ['name' => 'Hidden']));
        $third = $this->inTenant($tenantA, fn (SyncProtocol $sync) => $sync->publishChange('price', 'A-2', 'upsert', ['minor' => 100]));

        self::assertGreaterThan($first->cursor, $third->cursor);
        $page = $this->inTenant($tenantA, fn (SyncProtocol $sync) => $sync->pull($deviceA, 0, 10));
        self::assertSame(['A-1', 'A-2'], array_map(fn ($change) => $change->entityId, $page->changes));
        self::assertSame($third->cursor, $page->cursor);
        $tenantBPage = $this->inTenant($tenantB, fn (SyncProtocol $sync) => $sync->pull($deviceB, 0, 10));
        self::assertSame(['B-1'], array_map(fn ($change) => $change->entityId, $tenantBPage->changes));

        $this->expectException(DomainException::class);
        $this->inTenant($tenantA, fn (SyncProtocol $sync) => $sync->pull($deviceA, $first->cursor, 10));
    }

    #[Test]
    public function replay_returns_original_receipt_but_fingerprint_mismatch_is_rejected(): void
    {
        [$tenant, $device] = $this->deviceFixture('Replay');
        $command = $this->command(['saleId' => 'sale-1']);
        $first = $this->inTenant($tenant, fn (SyncProtocol $sync) => $sync->submit($device, $command));
        $replay = $this->inTenant($tenant, fn (SyncProtocol $sync) => $sync->submit($device, $command));
        self::assertSame('rejected', $first->status);
        self::assertEquals($first, $replay);
        self::assertSame(1, DB::table('sync_command_inbox')->count());

        $this->expectException(RuntimeException::class);
        $this->inTenant($tenant, fn (SyncProtocol $sync) => $sync->submit($device, new SyncCommandEnvelope('1', $command->commandId, $command->type, $command->occurredAt, ['saleId' => 'changed'])));
    }

    #[Test]
    public function device_and_tenant_boundaries_prevent_command_disclosure(): void
    {
        [$tenantA, $deviceA] = $this->deviceFixture('Scope A');
        [, $otherDeviceA] = $this->deviceFixture('Scope A2', $tenantA);
        [$tenantB] = $this->deviceFixture('Scope B');
        $command = $this->command(['saleId' => 'sale-1']);
        $this->inTenant($tenantA, fn (SyncProtocol $sync) => $sync->submit($deviceA, $command));

        foreach ([[$tenantA, $otherDeviceA], [$tenantB, $deviceA]] as [$tenant, $device]) {
            try {
                $this->inTenant($tenant, fn (SyncProtocol $sync) => $sync->retry($device, $command->commandId));
                self::fail('A command crossed its tenant/device boundary.');
            } catch (DomainException) {
                self::assertTrue(true);
            }
        }
    }

    #[Test]
    public function retry_and_conflict_outcomes_preserve_attempts_and_evidence(): void
    {
        [$tenant, $device] = $this->deviceFixture('Outcomes');
        $handler = new SequenceHandler([
            SyncCommandOutcome::retry('dependency_unavailable', 'Try later.'),
            SyncCommandOutcome::conflict('sale_changed', 'Sale state changed.', ['serverRevision' => 4, 'clientRevision' => 3]),
        ]);
        $this->app->instance(SyncCommandHandler::class, $handler);
        $command = $this->command(['saleId' => 'sale-1']);
        $first = $this->inTenant($tenant, fn (SyncProtocol $sync) => $sync->submit($device, $command));
        $second = $this->inTenant($tenant, fn (SyncProtocol $sync) => $sync->retry($device, $command->commandId));

        self::assertSame('retry_pending', $first->status);
        self::assertSame(1, $first->attempts);
        self::assertSame('conflict', $second->status);
        self::assertSame(2, $second->attempts);
        $conflict = DB::table('sync_conflicts')->first();
        self::assertSame('sale_changed', $conflict->conflict_code);
        self::assertSame(['serverRevision' => 4, 'clientRevision' => 3], json_decode($conflict->evidence, true));
        self::assertEquals($second, $this->inTenant($tenant, fn (SyncProtocol $sync) => $sync->retry($device, $command->commandId)));
    }

    private function command(array $payload): SyncCommandEnvelope
    {
        return new SyncCommandEnvelope('1', (string) Str::uuid(), 'sales.finalize.v1', '2026-08-08T10:00:00+03:00', $payload);
    }

    /** @return array{string, string} */
    private function deviceFixture(string $name, ?string $existingTenant = null): array
    {
        $tenant = $existingTenant === null ? Tenant::query()->create(['name' => "Tenant {$name}", 'jurisdiction_code' => 'KE', 'status' => 'active']) : Tenant::query()->findOrFail($existingTenant);
        $company = Company::query()->create(['tenant_id' => $tenant->getKey(), 'name' => "Company {$name}", 'status' => 'active']);
        $branch = Branch::query()->create(['tenant_id' => $tenant->getKey(), 'company_id' => $company->getKey(), 'code' => 'BR-'.Str::slug($name), 'name' => "Branch {$name}", 'status' => 'active']);
        $issued = $this->app->make(TenantScope::class)->run(TenantId::fromString((string) $tenant->getKey()), function () use ($branch, $name) {
            $manager = $this->app->make(RegisterDeviceManager::class);
            $register = $manager->createRegister((string) $branch->getKey(), 'POS-'.Str::slug($name), $name);
            $issued = $manager->issueDeviceEnrollment((string) $register->getKey(), $name, now()->addHour());
            $manager->consumeDeviceEnrollment($issued->token);

            return $issued;
        });

        return [(string) $tenant->getKey(), (string) $issued->device->public_id];
    }

    private function inTenant(string $tenantId, callable $operation): mixed
    {
        return $this->app->make(TenantScope::class)->run(TenantId::fromString($tenantId), fn () => $operation($this->app->make(SyncProtocol::class)));
    }
}

final class SequenceHandler implements SyncCommandHandler
{
    /** @param list<SyncCommandOutcome> $outcomes */
    public function __construct(private array $outcomes) {}

    public function handle(string $tenantId, string $deviceId, SyncCommandEnvelope $command): SyncCommandOutcome
    {
        return array_shift($this->outcomes) ?? SyncCommandOutcome::applied();
    }
}
