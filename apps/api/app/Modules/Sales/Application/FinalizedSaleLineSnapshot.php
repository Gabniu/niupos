<?php

declare(strict_types=1);

namespace App\Modules\Sales\Application;

final readonly class FinalizedSaleLineSnapshot
{
    public function __construct(
        public int $lineNumber,
        public string $variantId,
        public int $quantity,
        public int $unitPriceMinor,
        public int $netMinor,
        public int $taxMinor,
        public int $grossMinor,
        public string $taxCode,
        public int $taxRateBasisPoints,
        public string $taxMode,
    ) {}
}
