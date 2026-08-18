<?php

declare(strict_types=1);

namespace Tests\Feature\Modules\Search;

use App\Modules\Search\Application\Contracts\SearchProjection;
use App\Modules\Search\Application\SearchDocument;
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
}
