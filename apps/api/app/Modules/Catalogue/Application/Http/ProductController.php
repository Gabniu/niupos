<?php

declare(strict_types=1);

namespace App\Modules\Catalogue\Application\Http;

use App\Modules\Tenancy\Application\TenantContext;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

final readonly class ProductController
{
    public function __construct(private TenantContext $context) {}

    public function index(Request $request): JsonResponse
    {
        $search = trim((string) $request->query('search', ''));
        $rows = DB::table('catalogue_product_variants as variants')
            ->join('catalogue_products as products', function ($join): void {
                $join->on('products.id', '=', 'variants.product_id')->on('products.tenant_id', '=', 'variants.tenant_id');
            })
            ->leftJoin('catalogue_units_of_measure as units', function ($join): void {
                $join->on('units.id', '=', 'variants.unit_of_measure_id')->on('units.tenant_id', '=', 'variants.tenant_id');
            })
            ->where('variants.tenant_id', (string) $this->context->id())
            ->where('variants.status', 'active')
            ->where('products.status', 'active')
            ->when($search !== '', static fn ($query) => $query->where(static function ($query) use ($search): void {
                $query->where('products.name', 'like', "%{$search}%")
                    ->orWhere('variants.name', 'like', "%{$search}%")
                    ->orWhere('variants.sku', 'like', "%{$search}%");
            }))
            ->orderBy('products.name')
            ->orderBy('variants.name')
            ->limit(100)
            ->get(['variants.id', 'products.name as product_name', 'variants.name as variant_name', 'variants.sku', 'units.code as unit_code']);

        return new JsonResponse(['data' => $rows->map(static fn (object $row): array => [
            'id' => (string) $row->id,
            'name' => (string) $row->product_name,
            'variantName' => (string) $row->variant_name,
            'sku' => (string) $row->sku,
            'unitCode' => $row->unit_code === null ? null : (string) $row->unit_code,
        ])->values()->all()]);
    }
}
