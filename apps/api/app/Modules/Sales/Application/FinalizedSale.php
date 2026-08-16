<?php

declare(strict_types=1);

namespace App\Modules\Sales\Application;

use DateTimeImmutable;

final readonly class FinalizedSale
{
    public function __construct(
        public string $saleId,
        public string $shiftId,
        public string $registerId,
        public string $currencyCode,
        public int $netMinor,
        public int $taxMinor,
        public int $grossMinor,
        public int $lineCount,
        public DateTimeImmutable $finalizedAt,
    ) {}
}
