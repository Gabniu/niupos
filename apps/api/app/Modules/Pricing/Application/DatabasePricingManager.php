<?php

declare(strict_types=1);

namespace App\Modules\Pricing\Application;

use App\Modules\Catalogue\Application\Contracts\ActiveVariantLookup;
use App\Modules\Pricing\Application\Contracts\CheckoutQuoteProvider;
use App\Modules\Pricing\Application\Contracts\PricingManager;
use App\Modules\Pricing\Domain\PriceBook;
use App\Modules\Pricing\Domain\PricingStatus;
use App\Modules\Pricing\Domain\ProductPrice;
use App\Modules\Pricing\Domain\TaxCategory;
use App\Modules\Sync\Application\Contracts\SyncChangePublisher;
use App\Modules\Tenancy\Application\TenantContext;
use DateTimeImmutable;
use DateTimeInterface;
use DomainException;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

final readonly class DatabasePricingManager implements CheckoutQuoteProvider, PricingManager
{
    public function __construct(private TenantContext $tenantContext, private ActiveVariantLookup $variants, private SyncChangePublisher $sync) {}

    public function createTaxCategory(string $code, int $rateBasisPoints, bool $inclusive): TaxCategory
    {
        $code = mb_strtoupper(trim($code));
        if ($code === '' || $rateBasisPoints < 0 || $rateBasisPoints > 10000) {
            throw new InvalidArgumentException('Tax code and rate must be valid.');
        }

        return DB::transaction(function () use ($code, $rateBasisPoints, $inclusive): TaxCategory {
            $taxCategory = TaxCategory::query()->create(['tenant_id' => $this->tenantId(), 'code' => $code, 'rate_basis_points' => $rateBasisPoints, 'is_inclusive' => $inclusive, 'status' => PricingStatus::Active->value]);
            $this->sync->publishChange('pricing.tax_categories', (string) $taxCategory->getKey(), 'upsert', [
                'id' => (string) $taxCategory->getKey(), 'code' => $taxCategory->code, 'rateBasisPoints' => $taxCategory->rate_basis_points,
                'isInclusive' => (bool) $taxCategory->is_inclusive, 'status' => $taxCategory->status,
            ]);

            return $taxCategory;
        });
    }

    public function createPriceBook(string $name, string $currencyCode): PriceBook
    {
        $name = trim($name);
        $currencyCode = mb_strtoupper(trim($currencyCode));
        if ($name === '' || preg_match('/^[A-Z]{3}$/', $currencyCode) !== 1) {
            throw new InvalidArgumentException('Price book name and ISO currency code must be valid.');
        }

        return DB::transaction(function () use ($name, $currencyCode): PriceBook {
            $priceBook = PriceBook::query()->create(['tenant_id' => $this->tenantId(), 'name' => $name, 'currency_code' => $currencyCode, 'status' => PricingStatus::Active->value]);
            $this->sync->publishChange('pricing.price_books', (string) $priceBook->getKey(), 'upsert', [
                'id' => (string) $priceBook->getKey(), 'name' => $priceBook->name, 'currencyCode' => $priceBook->currency_code,
                'status' => $priceBook->status,
            ]);

            return $priceBook;
        });
    }

    public function createPrice(string $priceBookId, string $variantId, string $taxCategoryId, int $amountMinor, DateTimeInterface $effectiveFrom, ?DateTimeInterface $effectiveUntil = null): ProductPrice
    {
        if ($amountMinor < 0) {
            throw new InvalidArgumentException('Price cannot be negative.');
        }
        if ($effectiveUntil !== null && $effectiveUntil <= $effectiveFrom) {
            throw new InvalidArgumentException('Price validity window must end after it starts.');
        }
        $tenantId = $this->tenantId();
        if (! $this->variants->existsForCurrentTenant($variantId)) {
            throw new DomainException('Variant must be active and belong to the current tenant.');
        }

        return DB::transaction(function () use ($tenantId, $priceBookId, $variantId, $taxCategoryId, $amountMinor, $effectiveFrom, $effectiveUntil): ProductPrice {
            if (! PriceBook::query()->where('tenant_id', $tenantId)->where('status', PricingStatus::Active->value)->whereKey($priceBookId)->lockForUpdate()->exists()) {
                throw new DomainException('Price book must be active and belong to the current tenant.');
            }
            if (! TaxCategory::query()->where('tenant_id', $tenantId)->where('status', PricingStatus::Active->value)->whereKey($taxCategoryId)->exists()) {
                throw new DomainException('Tax category must be active and belong to the current tenant.');
            }
            $overlap = ProductPrice::query()->where('tenant_id', $tenantId)->where('price_book_id', $priceBookId)->where('product_variant_id', $variantId)
                ->where(function ($query) use ($effectiveUntil): void {
                    if ($effectiveUntil !== null) {
                        $query->where('effective_from', '<', $effectiveUntil);
                    }
                })
                ->where(function ($query) use ($effectiveFrom): void {
                    $query->whereNull('effective_until')->orWhere('effective_until', '>', $effectiveFrom);
                })->lockForUpdate()->exists();
            if ($overlap) {
                throw new DomainException('Price validity window overlaps an existing price.');
            }

            $price = ProductPrice::query()->create(['tenant_id' => $tenantId, 'price_book_id' => $priceBookId, 'product_variant_id' => $variantId, 'tax_category_id' => $taxCategoryId, 'amount_minor' => $amountMinor, 'effective_from' => $effectiveFrom, 'effective_until' => $effectiveUntil]);
            $this->sync->publishChange('pricing.product_prices', (string) $price->getKey(), 'upsert', [
                'id' => (string) $price->getKey(), 'priceBookId' => (string) $price->price_book_id, 'variantId' => (string) $price->product_variant_id,
                'taxCategoryId' => (string) $price->tax_category_id, 'amountMinor' => $price->amount_minor,
                'effectiveFrom' => $price->effective_from?->toISOString(), 'effectiveUntil' => $price->effective_until?->toISOString(),
            ]);

            return $price;
        });
    }

    public function resolvePrice(string $priceBookId, string $variantId, DateTimeInterface $at): ?ProductPrice
    {
        return ProductPrice::query()->where('tenant_id', $this->tenantId())->where('price_book_id', $priceBookId)->where('product_variant_id', $variantId)
            ->where('effective_from', '<=', $at)->where(fn ($query) => $query->whereNull('effective_until')->orWhere('effective_until', '>', $at))
            ->orderByDesc('effective_from')->first();
    }

    public function calculateTax(int $amountMinor, int $rateBasisPoints, bool $inclusive): TaxBreakdown
    {
        if ($amountMinor < 0 || $rateBasisPoints < 0 || $rateBasisPoints > 10000) {
            throw new InvalidArgumentException('Amount and tax rate must be valid.');
        }
        if ($inclusive) {
            $tax = $rateBasisPoints === 0 ? 0 : self::roundHalfUp(self::checkedMultiply($amountMinor, $rateBasisPoints), 10000 + $rateBasisPoints);

            return new TaxBreakdown($amountMinor - $tax, $tax, $amountMinor);
        }
        $tax = self::roundHalfUp(self::checkedMultiply($amountMinor, $rateBasisPoints), 10000);

        return new TaxBreakdown($amountMinor, $tax, self::checkedAdd($amountMinor, $tax));
    }

    public function deactivatePriceBook(string $priceBookId): void
    {
        $this->deactivateReference(PriceBook::class, 'pricing.price_books', $priceBookId, fn (PriceBook $book): array => [
            'id' => (string) $book->getKey(), 'name' => $book->name, 'currencyCode' => $book->currency_code, 'status' => $book->status,
        ]);
    }

    public function deactivateTaxCategory(string $taxCategoryId): void
    {
        $this->deactivateReference(TaxCategory::class, 'pricing.tax_categories', $taxCategoryId, fn (TaxCategory $tax): array => [
            'id' => (string) $tax->getKey(), 'code' => $tax->code, 'rateBasisPoints' => $tax->rate_basis_points,
            'isInclusive' => (bool) $tax->is_inclusive, 'status' => $tax->status,
        ]);
    }

    public function quote(string $priceBookId, string $variantId, int $quantity, string $currencyCode, DateTimeInterface $at): CheckoutLineQuote
    {
        if ($quantity <= 0) {
            throw new InvalidArgumentException('Checkout quantity must be positive.');
        }

        $currencyCode = mb_strtoupper(trim($currencyCode));
        if (preg_match('/^[A-Z]{3}$/', $currencyCode) !== 1) {
            throw new InvalidArgumentException('Checkout currency must be a valid ISO currency code.');
        }
        if (! $this->variants->existsForCurrentTenant($variantId)) {
            throw new DomainException('Variant must be active and belong to the current tenant.');
        }

        $tenantId = $this->tenantId();
        $book = PriceBook::query()->where('tenant_id', $tenantId)->where('status', PricingStatus::Active->value)->whereKey($priceBookId)->first();
        if ($book === null) {
            throw new DomainException('Price book must be active and belong to the current tenant.');
        }
        if ($book->currency_code !== $currencyCode) {
            throw new DomainException('Checkout currency does not match the price book.');
        }

        $price = $this->resolvePrice($priceBookId, $variantId, $at);
        if ($price === null) {
            throw new DomainException('No effective price exists for the checkout timestamp.');
        }
        $taxCategory = TaxCategory::query()->where('tenant_id', $tenantId)->where('status', PricingStatus::Active->value)->whereKey($price->tax_category_id)->first();
        if ($taxCategory === null) {
            throw new DomainException('The effective price tax category is not active.');
        }

        $lineAmount = self::checkedMultiply($price->amount_minor, $quantity);
        $tax = $this->calculateTax($lineAmount, $taxCategory->rate_basis_points, $taxCategory->is_inclusive);
        $quotedAt = DateTimeImmutable::createFromInterface($at);

        return new CheckoutLineQuote(
            $variantId,
            $quantity,
            $currencyCode,
            $price->amount_minor,
            $tax->netMinor,
            $tax->taxMinor,
            $tax->grossMinor,
            (string) $taxCategory->getKey(),
            $taxCategory->code,
            $taxCategory->rate_basis_points,
            $taxCategory->is_inclusive,
            (string) $book->getKey(),
            (string) $price->getKey(),
            $quotedAt,
        );
    }

    private function tenantId(): string
    {
        return (string) $this->tenantContext->id();
    }

    /** @param class-string<PriceBook|TaxCategory> $modelClass */
    private function deactivateReference(string $modelClass, string $entityType, string $id, callable $payload): void
    {
        $tenantId = $this->tenantId();
        DB::transaction(function () use ($modelClass, $entityType, $id, $payload, $tenantId): void {
            /** @var PriceBook|TaxCategory|null $model */
            $model = $modelClass::query()->where('tenant_id', $tenantId)->whereKey($id)->lockForUpdate()->first();
            if ($model === null) {
                throw new DomainException('Pricing reference must belong to the current tenant.');
            }
            $model->update(['status' => PricingStatus::Inactive->value]);
            $this->sync->publishChange($entityType, (string) $model->getKey(), 'upsert', $payload($model));
        });
    }

    private static function roundHalfUp(int $numerator, int $denominator): int
    {
        $quotient = intdiv($numerator, $denominator);
        $remainder = $numerator % $denominator;

        return self::checkedAdd($quotient, $remainder >= intdiv($denominator, 2) + ($denominator % 2) ? 1 : 0);
    }

    private static function checkedMultiply(int $left, int $right): int
    {
        if ($left < 0 || $right < 0 || ($left !== 0 && $right > intdiv(PHP_INT_MAX, $left))) {
            throw new InvalidArgumentException('Monetary arithmetic overflow.');
        }

        return $left * $right;
    }

    private static function checkedAdd(int $left, int $right): int
    {
        if ($left < 0 || $right < 0 || $left > PHP_INT_MAX - $right) {
            throw new InvalidArgumentException('Monetary arithmetic overflow.');
        }

        return $left + $right;
    }
}
