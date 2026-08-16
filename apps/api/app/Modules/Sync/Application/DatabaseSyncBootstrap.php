<?php

declare(strict_types=1);

namespace App\Modules\Sync\Application;

use App\Modules\Sync\Application\Contracts\SyncBootstrap;
use App\Modules\Tenancy\Application\TenantContext;
use DomainException;
use Illuminate\Support\Facades\DB;

final readonly class DatabaseSyncBootstrap implements SyncBootstrap
{
    public function __construct(private TenantContext $tenants) {}

    public function snapshot(string $devicePublicId): array
    {
        $tenantId = (string) $this->tenants->id();
        $device = DB::table('devices')->where('tenant_id', $tenantId)->where('public_id', trim($devicePublicId))->where('status', 'active')->first();
        if ($device === null) {
            throw new DomainException('An active tenant device is required.');
        }
        $now = now();

        return [
            'version' => '1',
            'cursor' => (int) (DB::table('sync_changes')->where('tenant_id', $tenantId)->max('cursor') ?? 0),
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
                'prices' => DB::table('pricing_product_prices as prices')->join('pricing_price_books as books', function ($join) use ($tenantId): void {
                    $join->on('books.id', '=', 'prices.price_book_id')->where('books.tenant_id', $tenantId)->where('books.status', 'active');
                })->join('pricing_tax_categories as taxes', function ($join) use ($tenantId): void {
                    $join->on('taxes.id', '=', 'prices.tax_category_id')->where('taxes.tenant_id', $tenantId)->where('taxes.status', 'active');
                })->join('catalogue_product_variants as variants', function ($join) use ($tenantId): void {
                    $join->on('variants.id', '=', 'prices.product_variant_id')->where('variants.tenant_id', $tenantId)->where('variants.status', 'active');
                })->where('prices.tenant_id', $tenantId)->where('prices.effective_from', '<=', $now)->where(fn ($query) => $query->whereNull('prices.effective_until')->orWhere('prices.effective_until', '>', $now))->orderBy('prices.id')->get(['prices.id', 'prices.price_book_id', 'prices.product_variant_id', 'prices.tax_category_id', 'prices.amount_minor', 'prices.effective_from', 'prices.effective_until'])->map(fn (object $row): array => [
                    'id' => (string) $row->id,
                    'priceBookId' => (string) $row->price_book_id,
                    'productVariantId' => (string) $row->product_variant_id,
                    'taxCategoryId' => (string) $row->tax_category_id,
                    'amountMinor' => (int) $row->amount_minor,
                    'effectiveFrom' => (string) $row->effective_from,
                    'effectiveUntil' => $row->effective_until ? (string) $row->effective_until : null,
                ])->all(),
            ],
        ];
    }

    /** @param list<string> $columns */
    private function rows(string $table, array $columns): array
    {
        return DB::table($table)->where('tenant_id', (string) $this->tenants->id())->where('status', 'active')->orderBy('id')->get($columns)->map(fn (object $row): array => get_object_vars($row))->all();
    }
}
