<?php

declare(strict_types=1);

namespace Tests\Feature\Modules\Search;

use App\Modules\Search\Application\ElasticsearchSearchProjection;
use App\Modules\Search\Application\SearchDocument;
use App\Modules\Tenancy\Application\TenantScope;
use App\Modules\Tenancy\Domain\Tenant;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

final class ElasticsearchSearchProjectionTest extends TestCase
{
    use RefreshDatabase;

    public function test_upsert_and_search_use_a_tenant_specific_alias(): void
    {
        $tenant = Tenant::query()->create(['name' => 'Elastic Search tenant', 'jurisdiction_code' => 'KE', 'status' => 'active']);
        Http::fake(static fn () => Http::response(['hits' => ['hits' => [[
            '_id' => 'variant-1',
            '_source' => ['document_type' => 'catalogue.product_variant', 'document_id' => 'variant-1', 'title' => 'Milk / Whole', 'searchable_text' => 'milk whole', 'payload' => ['sku' => 'MILK-1'], 'source_version' => 2],
        ]]],], 200));

        $this->app->make(TenantScope::class)->runFor((string) $tenant->getKey(), function () use ($tenant): void {
            $projection = new ElasticsearchSearchProjection($this->app->make(\App\Modules\Tenancy\Application\TenantContext::class));
            $projection->upsert(new SearchDocument('catalogue.product_variant', 'variant-1', 'Milk / Whole', 'Milk whole', ['sku' => 'MILK-1'], 2));
            self::assertSame('variant-1', $projection->search('milk')[0]->documentId);
            $requests = Http::recorded();
            self::assertStringContainsString(strtolower((string) $tenant->getKey()), (string) $requests[0][0]->url());
        });
    }

    public function test_rebuild_swaps_the_alias_after_bulk_indexing(): void
    {
        $tenant = Tenant::query()->create(['name' => 'Elastic rebuild tenant', 'jurisdiction_code' => 'KE', 'status' => 'active']);
        Http::fake(static fn ($request) => $request->method() === 'GET' ? Http::response([], 404) : Http::response(['errors' => false], 200));

        $count = $this->app->make(TenantScope::class)->runFor((string) $tenant->getKey(), function (): int {
            return (new ElasticsearchSearchProjection($this->app->make(\App\Modules\Tenancy\Application\TenantContext::class)))->rebuild([
                new SearchDocument('catalogue.product_variant', 'variant-1', 'Milk', 'milk', [], 1),
            ]);
        });

        self::assertSame(1, $count);
        self::assertTrue(collect(Http::recorded())->contains(static fn (array $record): bool => str_ends_with((string) $record[0]->url(), '/_aliases')));
    }
}
