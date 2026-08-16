<?php

declare(strict_types=1);

namespace App\Modules\Shifts\Application\Data;

final readonly class RecordedCashTender
{
    public function __construct(
        public string $movementId,
        public string $shiftId,
        public string $saleId,
        public int $amountMinor,
        public string $currencyCode,
    ) {}
}
