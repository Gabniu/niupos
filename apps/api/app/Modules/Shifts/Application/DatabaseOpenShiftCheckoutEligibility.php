<?php

declare(strict_types=1);

namespace App\Modules\Shifts\Application;

use App\Modules\Shifts\Application\Contracts\OpenShiftCheckoutEligibility;
use App\Modules\Shifts\Application\Data\EligibleOpenShift;
use App\Modules\Shifts\Domain\Shift;
use App\Modules\Tenancy\Application\TenantContext;
use DateTimeImmutable;
use DomainException;
use Illuminate\Support\Facades\DB;

final readonly class DatabaseOpenShiftCheckoutEligibility implements OpenShiftCheckoutEligibility
{
    private const REJECTION_MESSAGE = 'Checkout requires an eligible open shift.';

    public function __construct(private TenantContext $tenantContext) {}

    public function withEligibleOpenShift(string $registerId, string $actorUserId, callable $operation): mixed
    {
        $tenantId = (string) $this->tenantContext->id();

        return DB::transaction(function () use ($tenantId, $registerId, $actorUserId, $operation): mixed {
            $shift = Shift::query()
                ->where('tenant_id', $tenantId)
                ->where('register_id', $registerId)
                ->where('status', 'open')
                ->lockForUpdate()
                ->first();

            if ($shift === null
                || ! $this->activeRegisterExists($tenantId, $registerId)
                || ! $this->activeMembershipExists($tenantId, $actorUserId)) {
                throw new DomainException(self::REJECTION_MESSAGE);
            }

            $openedAt = $shift->opened_at;
            if (! $openedAt instanceof DateTimeImmutable) {
                throw new DomainException(self::REJECTION_MESSAGE);
            }

            return $operation(new EligibleOpenShift(
                tenantId: $tenantId,
                shiftId: (string) $shift->getKey(),
                registerId: (string) $shift->register_id,
                actorUserId: $actorUserId,
                currency: (string) $shift->currency,
                openedAt: $openedAt,
            ));
        }, 3);
    }

    private function activeRegisterExists(string $tenantId, string $registerId): bool
    {
        return DB::table('registers')
            ->where('tenant_id', $tenantId)
            ->where('id', $registerId)
            ->where('status', 'active')
            ->exists();
    }

    private function activeMembershipExists(string $tenantId, string $actorUserId): bool
    {
        return DB::table('tenant_memberships')
            ->where('tenant_id', $tenantId)
            ->where('user_id', $actorUserId)
            ->where('status', 'active')
            ->exists();
    }
}
