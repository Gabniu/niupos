<?php

declare(strict_types=1);

namespace App\Modules\Shifts\Application;

use App\Modules\Shifts\Application\Contracts\ShiftCashManager;
use App\Modules\Shifts\Domain\CashMovement;
use App\Modules\Shifts\Domain\Shift;
use App\Modules\Tenancy\Application\TenantContext;
use DomainException;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

final readonly class DatabaseShiftCashManager implements ShiftCashManager
{
    public function __construct(private TenantContext $tenantContext) {}

    public function openShift(string $registerId, string $openingUserId, int $openingFloatMinor, string $currency, string $idempotencyKey): Shift
    {
        $tenantId = (string) $this->tenantContext->id();
        $currency = self::currency($currency);
        $idempotencyKey = self::required($idempotencyKey, 'Idempotency key');
        if ($openingFloatMinor < 0) {
            throw new InvalidArgumentException('Opening float cannot be negative.');
        }

        return DB::transaction(function () use ($tenantId, $registerId, $openingUserId, $openingFloatMinor, $currency, $idempotencyKey): Shift {
            $existing = Shift::query()->where('tenant_id', $tenantId)->where('idempotency_key', $idempotencyKey)->first();
            if ($existing !== null) {
                if ($existing->register_id !== $registerId || $existing->opening_user_id !== $openingUserId || $existing->opening_float_minor !== $openingFloatMinor || $existing->currency !== $currency) {
                    throw new DomainException('Idempotency key was already used for a different shift operation.');
                }

                return $existing;
            }

            $register = DB::table('registers')->where('tenant_id', $tenantId)->where('id', $registerId)->lockForUpdate()->first();
            if ($register === null || $register->status !== 'active') {
                throw new DomainException('Register must be active and belong to the current tenant.');
            }
            self::assertActiveTenantUser($tenantId, $openingUserId);
            if (Shift::query()->where('tenant_id', $tenantId)->where('register_id', $registerId)->where('status', 'open')->exists()) {
                throw new DomainException('Register already has an open shift.');
            }

            return Shift::query()->create([
                'tenant_id' => $tenantId, 'register_id' => $registerId, 'opening_user_id' => $openingUserId,
                'status' => 'open', 'currency' => $currency, 'opening_float_minor' => $openingFloatMinor,
                'expected_cash_minor' => $openingFloatMinor, 'opened_at' => now(), 'idempotency_key' => $idempotencyKey,
            ]);
        }, 3);
    }

    public function recordCashMovement(string $shiftId, string $type, int $amountMinor, string $reason, string $actorUserId, string $idempotencyKey): CashMovement
    {
        $tenantId = (string) $this->tenantContext->id();
        if (! in_array($type, ['pay_in', 'pay_out'], true)) {
            throw new InvalidArgumentException('Cash movement type must be pay_in or pay_out.');
        }
        if ($amountMinor <= 0) {
            throw new InvalidArgumentException('Cash movement amount must be positive.');
        }
        $reason = self::required($reason, 'Cash movement reason');
        $idempotencyKey = self::required($idempotencyKey, 'Idempotency key');

        return DB::transaction(function () use ($tenantId, $shiftId, $type, $amountMinor, $reason, $actorUserId, $idempotencyKey): CashMovement {
            $existing = CashMovement::query()->where('tenant_id', $tenantId)->where('idempotency_key', $idempotencyKey)->first();
            if ($existing !== null) {
                if ($existing->shift_id !== $shiftId || $existing->type !== $type || $existing->amount_minor !== $amountMinor || $existing->reason !== $reason || $existing->actor_user_id !== $actorUserId) {
                    throw new DomainException('Idempotency key was already used for a different cash movement.');
                }

                return $existing;
            }

            $shift = Shift::query()->where('tenant_id', $tenantId)->whereKey($shiftId)->lockForUpdate()->first();
            if ($shift === null || $shift->status !== 'open') {
                throw new DomainException('Cash movement requires an open shift in the current tenant.');
            }
            self::assertActiveTenantUser($tenantId, $actorUserId);
            $expected = $type === 'pay_in' ? $shift->expected_cash_minor + $amountMinor : $shift->expected_cash_minor - $amountMinor;
            if ($expected < 0) {
                throw new DomainException('Cash pay-out cannot exceed expected drawer cash.');
            }

            $movement = CashMovement::query()->create([
                'tenant_id' => $tenantId, 'shift_id' => $shiftId, 'type' => $type, 'amount_minor' => $amountMinor,
                'currency' => $shift->currency, 'reason' => $reason, 'actor_user_id' => $actorUserId,
                'idempotency_key' => $idempotencyKey, 'occurred_at' => now(),
            ]);
            $shift->forceFill(['expected_cash_minor' => $expected])->save();

            return $movement;
        }, 3);
    }

    public function closeShift(string $shiftId, string $closingUserId, int $countedCashMinor): Shift
    {
        $tenantId = (string) $this->tenantContext->id();
        if ($countedCashMinor < 0) {
            throw new InvalidArgumentException('Counted cash cannot be negative.');
        }

        return DB::transaction(function () use ($tenantId, $shiftId, $closingUserId, $countedCashMinor): Shift {
            $shift = Shift::query()->where('tenant_id', $tenantId)->whereKey($shiftId)->lockForUpdate()->first();
            if ($shift === null || $shift->status !== 'open') {
                throw new DomainException('Only an open shift in the current tenant can be closed.');
            }
            self::assertActiveTenantUser($tenantId, $closingUserId);
            $shift->forceFill([
                'status' => 'closed', 'closing_user_id' => $closingUserId, 'counted_cash_minor' => $countedCashMinor,
                'variance_minor' => $countedCashMinor - $shift->expected_cash_minor, 'closed_at' => now(),
            ])->save();

            return $shift->refresh();
        }, 3);
    }

    private static function assertActiveTenantUser(string $tenantId, string $userId): void
    {
        if (! DB::table('tenant_memberships')->where('tenant_id', $tenantId)->where('user_id', $userId)->where('status', 'active')->exists()) {
            throw new DomainException('Actor must have an active membership in the current tenant.');
        }
    }

    private static function required(string $value, string $label): string
    {
        $value = trim($value);
        if ($value === '') {
            throw new InvalidArgumentException("{$label} cannot be empty.");
        }

        return $value;
    }

    private static function currency(string $currency): string
    {
        $currency = strtoupper(trim($currency));
        if (preg_match('/^[A-Z]{3}$/', $currency) !== 1) {
            throw new InvalidArgumentException('Currency must be a three-letter ISO 4217 code.');
        }

        return $currency;
    }
}
