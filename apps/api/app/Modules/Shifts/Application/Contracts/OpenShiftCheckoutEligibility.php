<?php

declare(strict_types=1);

namespace App\Modules\Shifts\Application\Contracts;

use App\Modules\Shifts\Application\Data\EligibleOpenShift;

interface OpenShiftCheckoutEligibility
{
    /**
     * Execute checkout work while holding a lock on the eligible open shift.
     *
     * The callback runs inside the same database transaction that owns the
     * shift-row lock. Any persistent checkout finalization must complete in
     * this callback for the lock to protect it from a concurrent shift close.
     *
     * @template TResult
     *
     * @param  callable(EligibleOpenShift): TResult  $operation
     * @return TResult
     */
    public function withEligibleOpenShift(string $registerId, string $actorUserId, callable $operation): mixed;
}
