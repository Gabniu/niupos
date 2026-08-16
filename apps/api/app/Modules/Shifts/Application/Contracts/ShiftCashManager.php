<?php

declare(strict_types=1);

namespace App\Modules\Shifts\Application\Contracts;

use App\Modules\Shifts\Domain\CashMovement;
use App\Modules\Shifts\Domain\Shift;

interface ShiftCashManager
{
    public function openShift(string $registerId, string $openingUserId, int $openingFloatMinor, string $currency, string $idempotencyKey): Shift;

    public function recordCashMovement(string $shiftId, string $type, int $amountMinor, string $reason, string $actorUserId, string $idempotencyKey): CashMovement;

    public function closeShift(string $shiftId, string $closingUserId, int $countedCashMinor): Shift;
}
