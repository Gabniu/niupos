<?php

declare(strict_types=1);

namespace App\Modules\Shifts\Application;

use App\Modules\Shifts\Application\Contracts\CashTenderRecorder;
use App\Modules\Shifts\Application\Data\RecordedCashTender;
use App\Modules\Shifts\Domain\CashMovement;
use App\Modules\Shifts\Domain\Shift;
use App\Modules\Tenancy\Application\TenantContext;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;
use RuntimeException;

final readonly class DatabaseCashTenderRecorder implements CashTenderRecorder
{
    public function __construct(private TenantContext $tenants) {}

    public function record(string $shiftId, string $saleId, string $actorUserId, int $amountMinor, string $currencyCode, string $idempotencyKey): RecordedCashTender
    {
        $tenantId = (string) $this->tenants->id();
        $currency = strtoupper(trim($currencyCode));
        $key = trim($idempotencyKey);
        if ($amountMinor <= 0 || ! preg_match('/^[A-Z]{3}$/', $currency) || $key === '' || strlen($key) > 128) {
            throw new InvalidArgumentException('Cash tender requires a positive amount, currency and bounded idempotency key.');
        }
        $reason = "sale:{$saleId}";

        return DB::transaction(function () use ($tenantId, $shiftId, $saleId, $actorUserId, $amountMinor, $currency, $key, $reason): RecordedCashTender {
            if (DB::getDriverName() === 'pgsql') {
                DB::select('SELECT pg_advisory_xact_lock(hashtextextended(?, 0))', ["cash-tender:{$tenantId}:{$key}"]);
            }
            $existing = CashMovement::query()->where('tenant_id', $tenantId)->where('idempotency_key', $key)->first();
            if ($existing instanceof CashMovement) {
                if ($existing->shift_id !== $shiftId || $existing->type !== 'sale_cash' || (int) $existing->amount_minor !== $amountMinor || $existing->currency !== $currency || $existing->actor_user_id !== $actorUserId || $existing->reason !== $reason) {
                    throw new RuntimeException('The idempotency key is already bound to another cash movement.');
                }

                return $this->result($existing, $saleId);
            }

            $shift = Shift::query()->where('tenant_id', $tenantId)->whereKey($shiftId)->lockForUpdate()->first();
            if (! $shift instanceof Shift || $shift->status !== 'open' || $shift->currency !== $currency) {
                throw new RuntimeException('Cash tender is unavailable for the active shift.');
            }
            $membershipExists = DB::table('tenant_memberships')->where('tenant_id', $tenantId)->where('user_id', $actorUserId)->where('status', 'active')->exists();
            if (! $membershipExists || $shift->opening_user_id !== $actorUserId) {
                throw new RuntimeException('Cash tender is unavailable for the active shift.');
            }
            if ((int) $shift->expected_cash_minor > PHP_INT_MAX - $amountMinor) {
                throw new RuntimeException('Expected drawer cash exceeds the supported integer range.');
            }
            $movement = CashMovement::query()->create([
                'tenant_id' => $tenantId, 'shift_id' => $shiftId, 'type' => 'sale_cash',
                'amount_minor' => $amountMinor, 'currency' => $currency, 'reason' => $reason,
                'actor_user_id' => $actorUserId, 'idempotency_key' => $key, 'occurred_at' => now(),
            ]);
            $shift->update(['expected_cash_minor' => (int) $shift->expected_cash_minor + $amountMinor]);

            return $this->result($movement, $saleId);
        });
    }

    private function result(CashMovement $movement, string $saleId): RecordedCashTender
    {
        return new RecordedCashTender((string) $movement->id, (string) $movement->shift_id, $saleId, (int) $movement->amount_minor, (string) $movement->currency);
    }
}
