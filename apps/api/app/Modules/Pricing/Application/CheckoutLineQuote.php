<?php

declare(strict_types=1);

namespace App\Modules\Pricing\Application;

use DateTimeImmutable;

final readonly class CheckoutLineQuote
{
    public function __construct(
        public string $variantId,
        public int $quantity,
        public string $currencyCode,
        public int $unitPriceMinor,
        public int $netMinor,
        public int $taxMinor,
        public int $grossMinor,
        public string $taxCategoryId,
        public string $taxCode,
        public int $taxRateBasisPoints,
        public bool $taxInclusive,
        public string $priceBookId,
        public string $priceId,
        public DateTimeImmutable $quotedAt,
    ) {}
}
