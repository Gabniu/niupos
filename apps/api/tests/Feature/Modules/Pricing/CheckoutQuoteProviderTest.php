<?php

declare(strict_types=1);

namespace Tests\Feature\Modules\Pricing;

use App\Modules\Catalogue\Application\Contracts\CatalogueManager;
use App\Modules\Catalogue\Domain\ProductVariant;
use App\Modules\Catalogue\Domain\UnitOfMeasure;
use App\Modules\Pricing\Application\Contracts\CheckoutQuoteProvider;
use App\Modules\Pricing\Application\Contracts\PricingManager;
use App\Modules\Pricing\Domain\TaxCategory;
use App\Modules\Tenancy\Application\TenantScope;
use App\Modules\Tenancy\Domain\Tenant;
use App\Modules\Tenancy\Domain\TenantId;
use Carbon\CarbonImmutable;
use DomainException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use InvalidArgumentException;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

final class CheckoutQuoteProviderTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function it_returns_complete_deterministic_exclusive_and_inclusive_snapshots(): void
    {
        [$tenantId, $variantId] = $this->catalogueFixture('Quote');
        $at = CarbonImmutable::parse('2026-08-08T10:30:00.123456Z');

        [$exclusiveBook, $exclusivePrice, $exclusiveTax, $inclusiveBook] = $this->inTenant($tenantId, function (PricingManager $manager) use ($variantId, $at): array {
            $exclusiveTax = $manager->createTaxCategory('VAT50', 5000, false);
            $inclusiveTax = $manager->createTaxCategory('VAT100', 10000, true);
            $exclusiveBook = $manager->createPriceBook('Exclusive', 'KES');
            $inclusiveBook = $manager->createPriceBook('Inclusive', 'KES');
            $exclusivePrice = $manager->createPrice((string) $exclusiveBook->getKey(), $variantId, (string) $exclusiveTax->getKey(), 1, $at->subDay());
            $manager->createPrice((string) $inclusiveBook->getKey(), $variantId, (string) $inclusiveTax->getKey(), 3, $at->subDay());

            return [(string) $exclusiveBook->getKey(), (string) $exclusivePrice->getKey(), (string) $exclusiveTax->getKey(), (string) $inclusiveBook->getKey()];
        });

        $exclusive = $this->quote($tenantId, $exclusiveBook, $variantId, 3, 'kes', $at);
        self::assertSame([$variantId, 3, 'KES', 1, 3, 2, 5], [$exclusive->variantId, $exclusive->quantity, $exclusive->currencyCode, $exclusive->unitPriceMinor, $exclusive->netMinor, $exclusive->taxMinor, $exclusive->grossMinor]);
        self::assertSame([$exclusiveTax, 'VAT50', 5000, false, $exclusiveBook, $exclusivePrice], [$exclusive->taxCategoryId, $exclusive->taxCode, $exclusive->taxRateBasisPoints, $exclusive->taxInclusive, $exclusive->priceBookId, $exclusive->priceId]);
        self::assertSame($at->format('Y-m-d\TH:i:s.uP'), $exclusive->quotedAt->format('Y-m-d\TH:i:s.uP'));

        $repeat = $this->quote($tenantId, $exclusiveBook, $variantId, 3, 'KES', $at);
        self::assertEquals($exclusive, $repeat);

        $inclusive = $this->quote($tenantId, $inclusiveBook, $variantId, 1, 'KES', $at);
        self::assertSame([1, 2, 3], [$inclusive->netMinor, $inclusive->taxMinor, $inclusive->grossMinor]);
    }

    #[Test]
    public function effective_windows_are_half_open_and_currency_must_match(): void
    {
        [$tenantId, $variantId] = $this->catalogueFixture('Boundary');
        $boundary = CarbonImmutable::parse('2026-09-01T00:00:00Z');
        [$bookId] = $this->inTenant($tenantId, function (PricingManager $manager) use ($variantId, $boundary): array {
            $tax = $manager->createTaxCategory('ZERO', 0, false);
            $book = $manager->createPriceBook('Retail', 'KES');
            $manager->createPrice((string) $book->getKey(), $variantId, (string) $tax->getKey(), 10, $boundary->subMonth(), $boundary);
            $manager->createPrice((string) $book->getKey(), $variantId, (string) $tax->getKey(), 20, $boundary);

            return [(string) $book->getKey()];
        });

        self::assertSame(10, $this->quote($tenantId, $bookId, $variantId, 1, 'KES', $boundary->subSecond())->unitPriceMinor);
        self::assertSame(20, $this->quote($tenantId, $bookId, $variantId, 1, 'KES', $boundary)->unitPriceMinor);

        $this->expectException(DomainException::class);
        $this->quote($tenantId, $bookId, $variantId, 1, 'USD', $boundary);
    }

    #[Test]
    public function invalid_missing_inactive_cross_tenant_and_overflow_quotes_are_rejected(): void
    {
        [$firstTenant, $variantId] = $this->catalogueFixture('First');
        [$secondTenant] = $this->catalogueFixture('Second');
        [$bookId, $taxId] = $this->inTenant($firstTenant, function (PricingManager $manager) use ($variantId): array {
            $tax = $manager->createTaxCategory('VAT', 1600, false);
            $book = $manager->createPriceBook('Retail', 'KES');
            $manager->createPrice((string) $book->getKey(), $variantId, (string) $tax->getKey(), PHP_INT_MAX, CarbonImmutable::parse('2026-01-01'));

            return [(string) $book->getKey(), (string) $tax->getKey()];
        });

        foreach ([0, -1] as $quantity) {
            try {
                $this->quote($firstTenant, $bookId, $variantId, $quantity, 'KES', CarbonImmutable::now());
                self::fail('Invalid quantity was accepted.');
            } catch (InvalidArgumentException) {
                self::assertTrue(true);
            }
        }

        foreach ([
            fn () => $this->quote($firstTenant, $bookId, $variantId, 2, 'KES', CarbonImmutable::now()),
            fn () => $this->quote($secondTenant, $bookId, $variantId, 1, 'KES', CarbonImmutable::now()),
            fn () => $this->quote($firstTenant, $bookId, $variantId, 1, 'KES', CarbonImmutable::parse('2025-01-01')),
        ] as $invalid) {
            try {
                $invalid();
                self::fail('Invalid quote was accepted.');
            } catch (DomainException|InvalidArgumentException) {
                self::assertTrue(true);
            }
        }

        TaxCategory::query()->whereKey($taxId)->update(['status' => 'inactive']);
        $this->expectException(DomainException::class);
        $this->quote($firstTenant, $bookId, $variantId, 1, 'KES', CarbonImmutable::now());
    }

    #[Test]
    public function inactive_variant_is_rejected_at_quote_time(): void
    {
        [$tenantId, $variantId] = $this->catalogueFixture('Inactive');
        [$bookId] = $this->inTenant($tenantId, function (PricingManager $manager) use ($variantId): array {
            $tax = $manager->createTaxCategory('ZERO', 0, false);
            $book = $manager->createPriceBook('Retail', 'KES');
            $manager->createPrice((string) $book->getKey(), $variantId, (string) $tax->getKey(), 100, CarbonImmutable::parse('2026-01-01'));

            return [(string) $book->getKey()];
        });
        ProductVariant::query()->whereKey($variantId)->update(['status' => 'inactive']);

        $this->expectException(DomainException::class);
        $this->quote($tenantId, $bookId, $variantId, 1, 'KES', CarbonImmutable::now());
    }

    /** @return array{string, string} */
    private function catalogueFixture(string $name): array
    {
        $tenant = Tenant::query()->create(['name' => $name, 'jurisdiction_code' => 'KE', 'status' => 'active']);
        $unit = UnitOfMeasure::query()->create(['tenant_id' => $tenant->getKey(), 'code' => 'EA', 'name' => 'Each', 'status' => 'active']);
        $variant = $this->inTenant((string) $tenant->getKey(), fn (PricingManager $unused) => $this->app->make(CatalogueManager::class)->createProductWithDefaultVariant('Item', 'SKU-'.bin2hex(random_bytes(3)), (string) $unit->getKey())->defaultVariant);

        return [(string) $tenant->getKey(), (string) $variant->getKey()];
    }

    private function quote(string $tenantId, string $bookId, string $variantId, int $quantity, string $currency, CarbonImmutable $at): mixed
    {
        return $this->inTenant($tenantId, fn (PricingManager $unused) => $this->app->make(CheckoutQuoteProvider::class)->quote($bookId, $variantId, $quantity, $currency, $at));
    }

    private function inTenant(string $tenantId, callable $callback): mixed
    {
        return $this->app->make(TenantScope::class)->run(TenantId::fromString($tenantId), fn () => $callback($this->app->make(PricingManager::class)));
    }
}
