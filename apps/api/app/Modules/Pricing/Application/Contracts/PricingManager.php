<?php

declare(strict_types=1);

namespace App\Modules\Pricing\Application\Contracts;

use App\Modules\Pricing\Application\TaxBreakdown;
use App\Modules\Pricing\Domain\PriceBook;
use App\Modules\Pricing\Domain\ProductPrice;
use App\Modules\Pricing\Domain\TaxCategory;
use DateTimeInterface;

interface PricingManager
{
    public function createTaxCategory(string $code, int $rateBasisPoints, bool $inclusive): TaxCategory;

    public function createPriceBook(string $name, string $currencyCode): PriceBook;

    public function updatePriceBook(string $priceBookId, string $name, string $currencyCode): PriceBook;

    public function updateTaxCategory(string $taxCategoryId, string $code, int $rateBasisPoints, bool $inclusive): TaxCategory;

    public function createPrice(string $priceBookId, string $variantId, string $taxCategoryId, int $amountMinor, DateTimeInterface $effectiveFrom, ?DateTimeInterface $effectiveUntil = null): ProductPrice;

    public function updatePrice(string $priceId, int $amountMinor, string $taxCategoryId, DateTimeInterface $effectiveFrom, ?DateTimeInterface $effectiveUntil = null): ProductPrice;

    public function resolvePrice(string $priceBookId, string $variantId, DateTimeInterface $at): ?ProductPrice;

    public function calculateTax(int $amountMinor, int $rateBasisPoints, bool $inclusive): TaxBreakdown;

    public function deactivatePriceBook(string $priceBookId): void;

    public function deactivateTaxCategory(string $taxCategoryId): void;

    public function deletePrice(string $priceId): void;

    public function deletePriceBook(string $priceBookId): void;

    public function deleteTaxCategory(string $taxCategoryId): void;
}
