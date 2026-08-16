<?php

declare(strict_types=1);

namespace Tests\Feature\Modules\Pricing;

use App\Modules\Catalogue\Application\Contracts\CatalogueManager;
use App\Modules\Catalogue\Domain\ProductVariant;
use App\Modules\Catalogue\Domain\UnitOfMeasure;
use App\Modules\Pricing\Application\Contracts\PricingManager;
use App\Modules\Tenancy\Application\TenantScope;
use App\Modules\Tenancy\Domain\Tenant;
use App\Modules\Tenancy\Domain\TenantId;
use Carbon\CarbonImmutable;
use DomainException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use InvalidArgumentException;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

final class PricingManagerTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function tax_calculation_uses_integer_half_up_rounding_for_inclusive_and_exclusive_amounts(): void
    {
        $manager = $this->app->make(PricingManager::class);

        $exclusive = $manager->calculateTax(1, 5000, false);
        self::assertSame([1, 1, 2], [$exclusive->netMinor, $exclusive->taxMinor, $exclusive->grossMinor]);

        $inclusive = $manager->calculateTax(3, 10000, true);
        self::assertSame([1, 2, 3], [$inclusive->netMinor, $inclusive->taxMinor, $inclusive->grossMinor]);

        $standard = $manager->calculateTax(11600, 1600, true);
        self::assertSame([10000, 1600, 11600], [$standard->netMinor, $standard->taxMinor, $standard->grossMinor]);
    }

    #[Test]
    public function it_resolves_the_one_price_effective_in_a_half_open_window(): void
    {
        [$tenantId, $variantId] = $this->catalogueFixture('Window');
        $start = CarbonImmutable::parse('2026-08-01T00:00:00Z');
        $boundary = CarbonImmutable::parse('2026-09-01T00:00:00Z');

        [$bookId] = $this->inTenant($tenantId, function (PricingManager $manager) use ($variantId, $start, $boundary): array {
            $tax = $manager->createTaxCategory('VAT16', 1600, true);
            $book = $manager->createPriceBook('Retail', 'kes');
            $manager->createPrice((string) $book->getKey(), $variantId, (string) $tax->getKey(), 11600, $start, $boundary);
            $manager->createPrice((string) $book->getKey(), $variantId, (string) $tax->getKey(), 12000, $boundary);

            return [(string) $book->getKey()];
        });

        self::assertSame(11600, $this->inTenant($tenantId, fn (PricingManager $manager) => $manager->resolvePrice($bookId, $variantId, $boundary->subSecond()))?->amount_minor);
        self::assertSame(12000, $this->inTenant($tenantId, fn (PricingManager $manager) => $manager->resolvePrice($bookId, $variantId, $boundary))?->amount_minor);
        self::assertNull($this->inTenant($tenantId, fn (PricingManager $manager) => $manager->resolvePrice($bookId, $variantId, $start->subSecond())));
        self::assertSame(4, $this->inTenant($tenantId, fn (): int => (int) \Illuminate\Support\Facades\DB::table('sync_changes')->whereIn('entity_type', ['pricing.tax_categories', 'pricing.price_books', 'pricing.product_prices'])->count()));
    }

    #[Test]
    public function overlapping_windows_are_rejected_but_adjacent_windows_are_accepted(): void
    {
        [$tenantId, $variantId] = $this->catalogueFixture('Overlap');
        $this->expectException(DomainException::class);

        $this->inTenant($tenantId, function (PricingManager $manager) use ($variantId): void {
            $tax = $manager->createTaxCategory('ZERO', 0, false);
            $book = $manager->createPriceBook('Retail', 'KES');
            $manager->createPrice((string) $book->getKey(), $variantId, (string) $tax->getKey(), 100, CarbonImmutable::parse('2026-01-01'), CarbonImmutable::parse('2026-02-01'));
            $manager->createPrice((string) $book->getKey(), $variantId, (string) $tax->getKey(), 110, CarbonImmutable::parse('2026-01-15'), CarbonImmutable::parse('2026-03-01'));
        });
    }

    #[Test]
    public function cross_tenant_or_inactive_catalogue_references_and_invalid_values_are_rejected(): void
    {
        [$firstTenant, $firstVariant] = $this->catalogueFixture('First');
        [$secondTenant] = $this->catalogueFixture('Second');

        try {
            $this->inTenant($secondTenant, function (PricingManager $manager) use ($firstVariant): void {
                $tax = $manager->createTaxCategory('VAT', 1600, true);
                $book = $manager->createPriceBook('Retail', 'KES');
                $manager->createPrice((string) $book->getKey(), $firstVariant, (string) $tax->getKey(), 100, CarbonImmutable::now());
            });
            self::fail('A cross-tenant variant was accepted.');
        } catch (DomainException) {
            self::assertTrue(true);
        }

        ProductVariant::query()->whereKey($firstVariant)->update(['status' => 'inactive']);
        $this->expectException(DomainException::class);
        $this->inTenant($firstTenant, function (PricingManager $manager) use ($firstVariant): void {
            $tax = $manager->createTaxCategory('VAT', 1600, true);
            $book = $manager->createPriceBook('Retail', 'KES');
            $manager->createPrice((string) $book->getKey(), $firstVariant, (string) $tax->getKey(), 100, CarbonImmutable::now());
        });
    }

    #[Test]
    public function tenant_price_resolution_isolated_and_invalid_inputs_fail(): void
    {
        [$firstTenant, $variantId] = $this->catalogueFixture('Isolation');
        [$secondTenant] = $this->catalogueFixture('Other');
        [$bookId] = $this->inTenant($firstTenant, function (PricingManager $manager) use ($variantId): array {
            $tax = $manager->createTaxCategory('VAT', 1600, false);
            $book = $manager->createPriceBook('Retail', 'KES');
            $manager->createPrice((string) $book->getKey(), $variantId, (string) $tax->getKey(), 100, CarbonImmutable::parse('2026-01-01'));

            return [(string) $book->getKey()];
        });
        self::assertNull($this->inTenant($secondTenant, fn (PricingManager $manager) => $manager->resolvePrice($bookId, $variantId, CarbonImmutable::now())));

        foreach ([fn (PricingManager $manager) => $manager->calculateTax(-1, 0, false), fn (PricingManager $manager) => $manager->createTaxCategory('BAD', -1, false)] as $invalid) {
            try {
                $invalid($this->app->make(PricingManager::class));
                self::fail('Invalid pricing input was accepted.');
            } catch (InvalidArgumentException) {
                self::assertTrue(true);
            }
        }
    }

    #[Test]
    public function deactivating_pricing_references_publishes_inactive_sync_projections(): void
    {
        [$tenantId, $variantId] = $this->catalogueFixture('Deactivate pricing');
        [$bookId, $taxId] = $this->inTenant($tenantId, function (PricingManager $manager) use ($variantId): array {
            $tax = $manager->createTaxCategory('VAT', 1600, true);
            $book = $manager->createPriceBook('Retail', 'KES');
            return [(string) $book->getKey(), (string) $tax->getKey()];
        });

        $this->inTenant($tenantId, function (PricingManager $manager) use ($bookId, $taxId): void {
            $manager->deactivatePriceBook($bookId);
            $manager->deactivateTaxCategory($taxId);
        });

        self::assertDatabaseHas('pricing_price_books', ['id' => $bookId, 'status' => 'inactive']);
        self::assertDatabaseHas('pricing_tax_categories', ['id' => $taxId, 'status' => 'inactive']);
        self::assertDatabaseHas('sync_changes', ['entity_type' => 'pricing.price_books', 'entity_id' => $bookId, 'operation' => 'upsert']);
        self::assertDatabaseHas('sync_changes', ['entity_type' => 'pricing.tax_categories', 'entity_id' => $taxId, 'operation' => 'upsert']);
    }

    /** @return array{string, string} */
    private function catalogueFixture(string $name): array
    {
        $tenant = Tenant::query()->create(['name' => $name, 'jurisdiction_code' => 'KE', 'status' => 'active']);
        $unit = UnitOfMeasure::query()->create(['tenant_id' => $tenant->getKey(), 'code' => 'EA', 'name' => 'Each', 'status' => 'active']);
        $variant = $this->inTenant((string) $tenant->getKey(), fn (PricingManager $unused) => $this->app->make(CatalogueManager::class)->createProductWithDefaultVariant('Item', 'SKU-'.bin2hex(random_bytes(3)), (string) $unit->getKey())->defaultVariant);

        return [(string) $tenant->getKey(), (string) $variant->getKey()];
    }

    private function inTenant(string $tenantId, callable $callback): mixed
    {
        return $this->app->make(TenantScope::class)->run(TenantId::fromString($tenantId), fn () => $callback($this->app->make(PricingManager::class)));
    }
}
