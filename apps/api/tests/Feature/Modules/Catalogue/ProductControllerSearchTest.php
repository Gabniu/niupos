<?php

declare(strict_types=1);

namespace Tests\Feature\Modules\Catalogue;

use App\Modules\Catalogue\Application\Contracts\CatalogueManager;
use App\Modules\Catalogue\Application\Http\ProductController;
use App\Modules\Catalogue\Domain\Category;
use App\Modules\Catalogue\Domain\UnitOfMeasure;
use App\Modules\Search\Application\Contracts\SearchProjection;
use App\Modules\Search\Application\SearchDocument;
use App\Modules\Tenancy\Application\TenantScope;
use App\Modules\Tenancy\Domain\Tenant;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Tests\TestCase;

final class ProductControllerSearchTest extends TestCase
{
    use RefreshDatabase;

    public function test_database_results_remain_correct_and_projection_can_order_them(): void
    {
        config(['search.driver' => 'elasticsearch']);
        $tenant = Tenant::query()->create(['name' => 'Projection catalogue', 'jurisdiction_code' => 'KE', 'status' => 'active']);
        $unit = UnitOfMeasure::query()->create(['tenant_id' => $tenant->getKey(), 'code' => 'EA', 'name' => 'Each', 'status' => 'active']);
        Category::query()->create(['tenant_id' => $tenant->getKey(), 'name' => 'Grocery', 'status' => 'active']);

        $this->app->make(TenantScope::class)->runFor((string) $tenant->getKey(), function () use ($unit): void {
            $first = $this->app->make(CatalogueManager::class)->createProductWithDefaultVariant('Milk A', 'MILK-A', (string) $unit->getKey());
            $second = $this->app->make(CatalogueManager::class)->createProductWithDefaultVariant('Milk B', 'MILK-B', (string) $unit->getKey());
            $this->app->instance(SearchProjection::class, new class((string) $second->defaultVariant->getKey(), (string) $first->defaultVariant->getKey()) implements SearchProjection
            {
                public function __construct(private string $second, private string $first) {}

                public function upsert(SearchDocument $document): void {}

                public function delete(string $documentType, string $documentId): void {}

                public function search(string $query, int $limit = 20): array
                {
                    return [
                        new SearchDocument('catalogue.product_variant', $this->second, 'Milk B', 'milk b', [], 1),
                        new SearchDocument('catalogue.product_variant', $this->first, 'Milk A', 'milk a', [], 1),
                    ];
                }

                public function rebuild(iterable $documents): int { return 0; }
            });
            $this->app->forgetScopedInstances();
            $response = $this->app->make(ProductController::class)->index(Request::create('/api/v1/catalogue/products', 'GET', ['search' => 'milk']));
            $data = $response->getData(true)['data'];
            self::assertSame('Milk B', $data[0]['name']);
            self::assertSame('Milk A', $data[1]['name']);
        });
    }
}
