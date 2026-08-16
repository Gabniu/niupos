<?php

declare(strict_types=1);

namespace App\Modules\Shifts\Application\Contracts;

use App\Modules\Shifts\Application\Data\RecordedCashTender;

interface CashTenderRecorder
{
    public function record(string $shiftId, string $saleId, string $actorUserId, int $amountMinor, string $currencyCode, string $idempotencyKey): RecordedCashTender;
}
