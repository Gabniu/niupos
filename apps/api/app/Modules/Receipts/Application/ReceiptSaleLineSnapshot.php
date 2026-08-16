<?php

declare(strict_types=1);

namespace App\Modules\Receipts\Application;

final readonly class ReceiptSaleLineSnapshot
{
    public function __construct(
        public int $lineNumber, public string $variantId, public ?string $description,
        public int $quantity, public int $unitPriceMinor, public int $netMinor,
        public int $taxMinor, public int $grossMinor, public string $taxCode,
        public int $taxRateBasisPoints, public bool $taxInclusive,
    ) {}
}
