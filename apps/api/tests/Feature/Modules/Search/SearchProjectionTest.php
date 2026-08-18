<?php

declare(strict_types=1);

namespace Tests\Feature\Modules\Search;

use App\Modules\Search\Application\Contracts\CatalogueSearchRebuilder;
use App\Modules\Search\Application\Contracts\SearchProjection;
use App\Modules\Search\Application\SearchDocument;
use App\Modules\Catalogue\Application\Contracts\CatalogueManager;
use App\Modules\Catalogue\Domain\UnitOfMeasure;
use App\Modules\Tenancy\Application\TenantScope;
use App\Modules\Tenancy\Domain\Tenant;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

final class SearchProjectionTest extends TestCase
{
    use RefreshDatabase;

    public function test_projection_is_tenant_scoped_and_rebuildable(): void
    {
        $tenantA = Tenant::query()->create(['name' => 'Search A', 'jurisdiction_code' => 'KE', 'status' => 'active']);
        $tenantB = Tenant::query()->create(['name' => 'Search B', 'jurisdiction_code' => 'KE', 'status' => 'active']);
        $milk = new SearchDocument('catalogue.product', 'product-a', 'Milk', 'Fresh whole milk', ['sku' => 'MILK-1'], 4);
        $bread = new SearchDocument('catalogue.product', 'product-b', 'Bread', 'Sliced bread', ['sku' => 'BREAD-1'], 5);

        $this->app->make(TenantScope::class)->runFor((string) $tenantA->getKey(), function () use ($milk, $bread): void {
            $projection = $this->app->make(SearchProjection::class);
            $projection->upsert($milk);
            $projection->upsert($bread);
            self::assertCount(1, $projection->search('milk'));
            self::assertSame(1, $projection->rebuild([$milk]));
        });

        $this->app->make(TenantScope::class)->runFor((string) $tenantB->getKey(), function (): void {
            self::assertSame([], $this->app->make(SearchProjection::class)->search('milk'));
        });
    }

    public function test_delete_removes_only_the_current_tenant_document(): void
    {
        $tenant = Tenant::query()->create(['name' => 'Delete Search', 'jurisdiction_code' => 'KE', 'status' => 'active']);
        $document = new SearchDocument('catalogue.product', 'product-a', 'Milk', 'Fresh whole milk', [], 1);

        $this->app->make(TenantScope::class)->runFor((string) $tenant->getKey(), function () use ($document): void {
            $projection = $this->app->make(SearchProjection::class);
            $projection->upsert($document);
            $projection->delete($document->documentType, $document->documentId);
            self::assertSame([], $projection->search('milk'));
        });
    }

    public function test_catalogue_rebuild_publishes_active_variants_without_cross_tenant_data(): void
    {
        $tenantA = Tenant::query()->create(['name' => 'Catalogue Search A', 'jurisdiction_code' => 'KE', 'status' => 'active']);
        $tenantB = Tenant::query()->create(['name' => 'Catalogue Search B', 'jurisdiction_code' => 'KE', 'status' => 'active']);
        $unitA = UnitOfMeasure::query()->create(['tenant_id' => $tenantA->getKey(), 'code' => 'EA', 'name' => 'Each', 'status' => 'active']);
        UnitOfMeasure::query()->create(['tenant_id' => $tenantB->getKey(), 'code' => 'EA', 'name' => 'Each', 'status' => 'active']);

        $this->app->make(TenantScope::class)->runFor((string) $tenantA->getKey(), function () use ($unitA): void {
            $this->app->make(CatalogueManager::class)->createProductWithDefaultVariant('Whole Milk', 'MILK-1', (string) $unitA->getKey(), barcode: '6161 2345');
            self::assertSame(1, $this->app->make(CatalogueSearchRebuilder::class)->rebuild());
            $results = $this->app->make(SearchProjection::class)->search('61612345');
            self::assertCount(1, $results);
            self::assertSame('catalogue.product_variant', $results[0]->documentType);
        });

        $this->app->make(TenantScope::class)->runFor((string) $tenantB->getKey(), function (): void {
            self::assertSame([], $this->app->make(SearchProjection::class)->search('61612345'));
        });
    }
}
