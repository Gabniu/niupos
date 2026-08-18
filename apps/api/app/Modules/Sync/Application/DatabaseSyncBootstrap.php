<?php

declare(strict_types=1);

namespace App\Modules\Sync\Application;

use App\Modules\Sync\Application\Contracts\SyncBootstrap;
use App\Modules\Tenancy\Application\TenantContext;
use DomainException;
use RuntimeException;
use Illuminate\Support\Facades\DB;

final readonly class DatabaseSyncBootstrap implements SyncBootstrap
{
    public function __construct(private TenantContext $tenants) {}

    public function snapshot(string $devicePublicId, ?array $page = null): array
    {
        $tenantId = (string) $this->tenants->id();
        $device = DB::table('devices')->where('tenant_id', $tenantId)->where('public_id', trim($devicePublicId))->where('status', 'active')->first();
        if ($device === null) {
            throw new DomainException('An active tenant device is required.');
        }
        $now = now();
        $cursor = (int) (DB::table('sync_changes')->where('tenant_id', $tenantId)->max('cursor') ?? 0);

        if ($page !== null) {
            $snapshotCursor = (int) ($page['snapshot_cursor'] ?? $cursor);
            if ($snapshotCursor !== $cursor) {
                throw new RuntimeException('SYNC_BOOTSTRAP_CHANGED');
            }

            return $this->pageSnapshot($tenantId, $now, $cursor, $page);
        }

        return [
            'version' => '1',
            'cursor' => $cursor,
            'generatedAt' => $now->toISOString(),
            'catalogue' => [
                'categories' => $this->rows('catalogue_categories', ['id', 'name', 'status']),
                'unitsOfMeasure' => $this->rows('catalogue_units_of_measure', ['id', 'code', 'name', 'status']),
                'products' => $this->rows('catalogue_products', ['id', 'category_id', 'name', 'status']),
                'variants' => $this->rows('catalogue_product_variants', ['id', 'product_id', 'unit_of_measure_id', 'name', 'sku', 'status']),
                'barcodes' => $this->rows('catalogue_barcodes', ['id', 'product_variant_id', 'value', 'status']),
            ],
            'pricing' => [
                'taxCategories' => $this->rows('pricing_tax_categories', ['id', 'code', 'rate_basis_points', 'is_inclusive', 'status']),
                'priceBooks' => $this->rows('pricing_price_books', ['id', 'name', 'currency_code', 'status']),
                'prices' => $this->pricingPrices($tenantId, $now),
            ],
        ];
    }

    /** @param array{section:string,collection:string,after_id?:string,limit?:int,snapshot_cursor?:int} $page */
    private function pageSnapshot(string $tenantId, object $now, int $cursor, array $page): array
    {
        $section = $page['section'];
        $collection = $page['collection'];
        $limit = $page['limit'] ?? 100;
        $afterId = $page['after_id'] ?? null;
        $collections = $section === 'catalogue'
            ? ['categories', 'unitsOfMeasure', 'products', 'variants', 'barcodes']
            : ['taxCategories', 'priceBooks', 'prices'];
        if (! in_array($collection, $collections, true)) {
            throw new DomainException('The bootstrap collection is invalid.');
        }

        $catalogue = array_fill_keys(['categories', 'unitsOfMeasure', 'products', 'variants', 'barcodes'], []);
        $pricing = array_fill_keys(['taxCategories', 'priceBooks', 'prices'], []);
        $tableDefinitions = [
            'categories' => ['catalogue_categories', ['id', 'name', 'status']],
            'unitsOfMeasure' => ['catalogue_units_of_measure', ['id', 'code', 'name', 'status']],
            'products' => ['catalogue_products', ['id', 'category_id', 'name', 'status']],
            'variants' => ['catalogue_product_variants', ['id', 'product_id', 'unit_of_measure_id', 'name', 'sku', 'status']],
            'barcodes' => ['catalogue_barcodes', ['id', 'product_variant_id', 'value', 'status']],
            'taxCategories' => ['pricing_tax_categories', ['id', 'code', 'rate_basis_points', 'is_inclusive', 'status']],
            'priceBooks' => ['pricing_price_books', ['id', 'name', 'currency_code', 'status']],
        ];

        if ($collection === 'prices') {
            [$rows, $nextAfterId, $hasMore] = $this->pricingPricePage($tenantId, $now, $afterId, $limit);
        } else {
            [$table, $columns] = $tableDefinitions[$collection];
            [$rows, $nextAfterId, $hasMore] = $this->rowsPage($table, $columns, $afterId, $limit);
        }
        if ($section === 'catalogue') {
            $catalogue[$collection] = $rows;
        } else {
            $pricing[$collection] = $rows;
        }

        return [
            'version' => '1', 'cursor' => $cursor, 'generatedAt' => $now->toISOString(),
            'catalogue' => $catalogue, 'pricing' => $pricing,
            'page' => ['section' => $section, 'collection' => $collection, 'afterId' => $afterId, 'nextAfterId' => $nextAfterId, 'hasMore' => $hasMore, 'limit' => $limit],
        ];
    }

    /** @param list<string> $columns */
    private function rows(string $table, array $columns): array
    {
        return DB::table($table)->where('tenant_id', (string) $this->tenants->id())->where('status', 'active')->orderBy('id')->get($columns)->map(fn (object $row): array => get_object_vars($row))->all();
    }

    /** @param list<string> $columns @return array{0:list<array<string,mixed>>,1:?string,2:bool} */
    private function rowsPage(string $table, array $columns, ?string $afterId, int $limit): array
    {
        $query = DB::table($table)->where('tenant_id', (string) $this->tenants->id())->where('status', 'active')->orderBy('id');
        if ($afterId !== null) {
            $query->where('id', '>', $afterId);
        }
        $items = $query->limit($limit + 1)->get($columns)->map(fn (object $row): array => get_object_vars($row))->all();
        $hasMore = count($items) > $limit;
        if ($hasMore) {
            array_pop($items);
        }
        $nextAfterId = $hasMore && $items !== [] ? (string) ($items[array_key_last($items)]['id'] ?? '') : null;

        return [$items, $nextAfterId !== '' ? $nextAfterId : null, $hasMore];
    }

    private function pricingPrices(string $tenantId, object $now): array
    {
        return $this->pricingPriceQuery($tenantId, $now)->get(['prices.id', 'prices.price_book_id', 'prices.product_variant_id', 'prices.tax_category_id', 'prices.amount_minor', 'prices.effective_from', 'prices.effective_until'])->map(fn (object $row): array => $this->priceRow($row))->all();
    }

    /** @return array{0:list<array<string,mixed>>,1:?string,2:bool} */
    private function pricingPricePage(string $tenantId, object $now, ?string $afterId, int $limit): array
    {
        $query = $this->pricingPriceQuery($tenantId, $now)->orderBy('prices.id');
        if ($afterId !== null) {
            $query->where('prices.id', '>', $afterId);
        }
        $items = $query->limit($limit + 1)->get(['prices.id', 'prices.price_book_id', 'prices.product_variant_id', 'prices.tax_category_id', 'prices.amount_minor', 'prices.effective_from', 'prices.effective_until'])->map(fn (object $row): array => $this->priceRow($row))->all();
        $hasMore = count($items) > $limit;
        if ($hasMore) {
            array_pop($items);
        }
        $nextAfterId = $hasMore && $items !== [] ? (string) ($items[array_key_last($items)]['id'] ?? '') : null;

        return [$items, $nextAfterId !== '' ? $nextAfterId : null, $hasMore];
    }

    private function pricingPriceQuery(string $tenantId, object $now): mixed
    {
        return DB::table('pricing_product_prices as prices')->join('pricing_price_books as books', function ($join) use ($tenantId): void {
            $join->on('books.id', '=', 'prices.price_book_id')->where('books.tenant_id', $tenantId)->where('books.status', 'active');
        })->join('pricing_tax_categories as taxes', function ($join) use ($tenantId): void {
            $join->on('taxes.id', '=', 'prices.tax_category_id')->where('taxes.tenant_id', $tenantId)->where('taxes.status', 'active');
        })->join('catalogue_product_variants as variants', function ($join) use ($tenantId): void {
            $join->on('variants.id', '=', 'prices.product_variant_id')->where('variants.tenant_id', $tenantId)->where('variants.status', 'active');
        })->where('prices.tenant_id', $tenantId)->where('prices.effective_from', '<=', $now)->where(fn ($query) => $query->whereNull('prices.effective_until')->orWhere('prices.effective_until', '>', $now));
    }

    /** @return array<string,mixed> */
    private function priceRow(object $row): array
    {
        return ['id' => (string) $row->id, 'priceBookId' => (string) $row->price_book_id, 'productVariantId' => (string) $row->product_variant_id, 'taxCategoryId' => (string) $row->tax_category_id, 'amountMinor' => (int) $row->amount_minor, 'effectiveFrom' => (string) $row->effective_from, 'effectiveUntil' => $row->effective_until ? (string) $row->effective_until : null];
    }
}
