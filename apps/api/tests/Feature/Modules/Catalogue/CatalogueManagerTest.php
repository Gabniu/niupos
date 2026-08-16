<?php

declare(strict_types=1);

namespace Tests\Feature\Modules\Catalogue;

use App\Modules\Catalogue\Application\Contracts\CatalogueManager;
use App\Modules\Catalogue\Domain\Barcode;
use App\Modules\Catalogue\Domain\Category;
use App\Modules\Catalogue\Domain\UnitOfMeasure;
use App\Modules\Tenancy\Application\TenantScope;
use App\Modules\Tenancy\Domain\Tenant;
use App\Modules\Tenancy\Domain\TenantId;
use DomainException;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

final class CatalogueManagerTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function it_creates_and_resolves_a_normalized_tenant_catalogue_identity(): void
    {
        [$tenantId, $unitId, $categoryId] = $this->tenantFixture('Alpha');

        $created = $this->inTenant($tenantId, fn (CatalogueManager $manager) => $manager->createProductWithDefaultVariant(
            '  Whole Milk  ', ' milk 001 ', $unitId, $categoryId, ' 6161 2345 ',
        ));

        self::assertSame('Whole Milk', $created->product->name);
        self::assertSame('MILK001', $created->defaultVariant->normalized_sku);
        self::assertSame('61612345', $created->barcode?->normalized_value);
        self::assertDatabaseHas('sync_changes', ['tenant_id' => $tenantId, 'entity_type' => 'catalogue.products', 'entity_id' => $created->product->getKey(), 'operation' => 'upsert']);
        self::assertDatabaseHas('sync_changes', ['tenant_id' => $tenantId, 'entity_type' => 'catalogue.variants', 'entity_id' => $created->defaultVariant->getKey(), 'operation' => 'upsert']);
        $resolved = $this->inTenant($tenantId, fn (CatalogueManager $manager) => $manager->resolveBarcode('6161 2345'));
        self::assertSame($created->defaultVariant->getKey(), $resolved?->getKey());
    }

    #[Test]
    public function barcode_resolution_is_exactly_tenant_scoped_and_unknown_identity_returns_null(): void
    {
        [$firstTenant, $firstUnit] = $this->tenantFixture('First');
        [$secondTenant] = $this->tenantFixture('Second');
        $created = $this->inTenant($firstTenant, fn (CatalogueManager $manager) => $manager->createProductWithDefaultVariant('Tea', 'TEA-1', $firstUnit, barcode: '123456'));

        self::assertNull($this->inTenant($secondTenant, fn (CatalogueManager $manager) => $manager->resolveBarcode('123456')));
        self::assertNull($this->inTenant($firstTenant, fn (CatalogueManager $manager) => $manager->resolveBarcode('000000')));
        self::assertSame($created->defaultVariant->getKey(), $this->inTenant($firstTenant, fn (CatalogueManager $manager) => $manager->resolveBarcode('123456'))?->getKey());
    }

    #[Test]
    public function deactivating_a_product_propagates_to_children_and_sync_feed(): void
    {
        [$tenantId, $unitId] = $this->tenantFixture('Deactivate');
        $created = $this->inTenant($tenantId, fn (CatalogueManager $manager) => $manager->createProductWithDefaultVariant('Tea', 'TEA-DEACT', $unitId, barcode: '999999'));

        $this->inTenant($tenantId, fn (CatalogueManager $manager) => $manager->deactivateProduct((string) $created->product->getKey()));

        self::assertNull($this->inTenant($tenantId, fn (CatalogueManager $manager) => $manager->resolveBarcode('999999')));
        self::assertDatabaseHas('catalogue_products', ['id' => $created->product->getKey(), 'status' => 'inactive']);
        self::assertDatabaseHas('catalogue_product_variants', ['id' => $created->defaultVariant->getKey(), 'status' => 'inactive']);
        self::assertDatabaseHas('sync_changes', ['entity_type' => 'catalogue.products', 'entity_id' => $created->product->getKey(), 'operation' => 'upsert']);
    }

    #[Test]
    public function duplicate_normalized_sku_and_barcode_are_rejected_within_a_tenant(): void
    {
        [$tenantId, $unitId] = $this->tenantFixture('Duplicate');
        $this->inTenant($tenantId, fn (CatalogueManager $manager) => $manager->createProductWithDefaultVariant('Rice', ' rice 01 ', $unitId, barcode: '111 222'));

        try {
            $this->inTenant($tenantId, fn (CatalogueManager $manager) => $manager->createProductWithDefaultVariant('Rice Two', 'RICE01', $unitId));
            self::fail('A duplicate normalized SKU was accepted.');
        } catch (QueryException) {
            self::assertTrue(true);
        }

        $this->expectException(QueryException::class);
        $this->inTenant($tenantId, fn (CatalogueManager $manager) => $manager->createProductWithDefaultVariant('Beans', 'BEANS-1', $unitId, barcode: '111222'));
    }

    #[Test]
    public function cross_tenant_category_and_unit_references_are_rejected(): void
    {
        [$firstTenant, $firstUnit, $firstCategory] = $this->tenantFixture('First refs');
        [$secondTenant, $secondUnit, $secondCategory] = $this->tenantFixture('Second refs');

        foreach ([[$secondUnit, $firstCategory], [$firstUnit, $secondCategory]] as [$unit, $category]) {
            try {
                $this->inTenant($firstTenant, fn (CatalogueManager $manager) => $manager->createProductWithDefaultVariant('Invalid', 'SKU-'.bin2hex(random_bytes(3)), $unit, $category));
                self::fail('A cross-tenant catalogue reference was accepted.');
            } catch (DomainException) {
                self::assertTrue(true);
            }
        }

        self::assertNull($this->inTenant($secondTenant, fn (CatalogueManager $manager) => $manager->resolveBarcode('not-known')));
    }

    #[Test]
    public function database_composite_keys_reject_a_cross_tenant_barcode_variant_reference(): void
    {
        [$firstTenant, $firstUnit] = $this->tenantFixture('First constraint');
        [$secondTenant] = $this->tenantFixture('Second constraint');
        $created = $this->inTenant($firstTenant, fn (CatalogueManager $manager) => $manager->createProductWithDefaultVariant('Flour', 'FLOUR-1', $firstUnit));

        $this->expectException(QueryException::class);
        Barcode::query()->create([
            'tenant_id' => $secondTenant,
            'product_variant_id' => $created->defaultVariant->getKey(),
            'value' => '998877',
            'normalized_value' => '998877',
            'status' => 'active',
        ]);
    }

    /** @return array{string, string, string} */
    private function tenantFixture(string $name): array
    {
        $tenant = Tenant::query()->create(['name' => $name, 'jurisdiction_code' => 'KE', 'status' => 'active']);
        $unit = UnitOfMeasure::query()->create(['tenant_id' => $tenant->getKey(), 'code' => 'EA', 'name' => 'Each', 'status' => 'active']);
        $category = Category::query()->create(['tenant_id' => $tenant->getKey(), 'name' => 'General', 'status' => 'active']);

        return [(string) $tenant->getKey(), (string) $unit->getKey(), (string) $category->getKey()];
    }

    private function inTenant(string $tenantId, callable $callback): mixed
    {
        return $this->app->make(TenantScope::class)->run(
            TenantId::fromString($tenantId),
            fn () => $callback($this->app->make(CatalogueManager::class)),
        );
    }
}
