<?php

declare(strict_types=1);

namespace App\Modules\Search\Application;

use App\Modules\Search\Application\Contracts\CatalogueSearchRebuilder;
use App\Modules\Search\Application\Contracts\SearchProjection;
use App\Modules\Tenancy\Application\TenantContext;
use Illuminate\Support\Facades\DB;

final readonly class DatabaseCatalogueSearchRebuilder implements CatalogueSearchRebuilder
{
    public function __construct(
        private TenantContext $tenantContext,
        private SearchProjection $projection,
    ) {}

    public function rebuild(): int
    {
        $tenantId = (string) $this->tenantContext->id();
        $barcodes = DB::table('catalogue_barcodes')
            ->where('tenant_id', $tenantId)
            ->where('status', 'active')
            ->get(['product_variant_id', 'value', 'normalized_value'])
            ->groupBy('product_variant_id');

        $documents = DB::table('catalogue_product_variants as variants')
            ->join('catalogue_products as products', function ($join): void {
                $join->on('products.id', '=', 'variants.product_id')
                    ->on('products.tenant_id', '=', 'variants.tenant_id');
            })
            ->where('variants.tenant_id', $tenantId)
            ->where('variants.status', 'active')
            ->where('products.status', 'active')
            ->orderBy('variants.id')
            ->get([
                'variants.id as variant_id',
                'variants.product_id',
                'variants.name as variant_name',
                'variants.sku',
                'variants.normalized_sku',
                'variants.updated_at as variant_updated_at',
                'products.name as product_name',
                'products.updated_at as product_updated_at',
            ])
            ->map(function (object $row) use ($barcodes): SearchDocument {
                $variantBarcodes = $barcodes->get((string) $row->variant_id, collect())
                    ->map(static fn (object $barcode): array => [
                        'value' => (string) $barcode->value,
                        'normalizedValue' => (string) $barcode->normalized_value,
                    ])->values()->all();
                $barcodeText = implode(' ', array_map(static fn (array $barcode): string => $barcode['value'], $variantBarcodes));
                $title = trim((string) $row->product_name.' / '.(string) $row->variant_name);
                $searchableText = trim(implode(' ', [
                    $row->product_name,
                    $row->variant_name,
                    $row->sku,
                    $row->normalized_sku,
                    $barcodeText,
                ]));
                $updatedAt = max(
                    (int) strtotime((string) $row->variant_updated_at),
                    (int) strtotime((string) $row->product_updated_at),
                    0,
                );

                return new SearchDocument(
                    'catalogue.product_variant',
                    (string) $row->variant_id,
                    $title,
                    $searchableText,
                    [
                        'productId' => (string) $row->product_id,
                        'variantId' => (string) $row->variant_id,
                        'productName' => (string) $row->product_name,
                        'variantName' => (string) $row->variant_name,
                        'sku' => (string) $row->sku,
                        'normalizedSku' => (string) $row->normalized_sku,
                        'barcodes' => $variantBarcodes,
                        'status' => 'active',
                    ],
                    $updatedAt,
                );
            })->all();

        return $this->projection->rebuild($documents);
    }
}
