<?php

declare(strict_types=1);

namespace App\Modules\Inventory\Application\Http;

use App\Modules\Tenancy\Application\TenantContext;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

final readonly class InventoryController
{
    public function __construct(private TenantContext $context) {}

    public function index(Request $request): JsonResponse
    {
        $tenantId = (string) $this->context->id();
        $warehouseId = trim((string) $request->query('warehouseId', ''));

        $query = DB::table('inventory_stock_balances as balances')
            ->join('warehouses', function ($join): void {
                $join->on('warehouses.id', '=', 'balances.warehouse_id')
                    ->on('warehouses.tenant_id', '=', 'balances.tenant_id');
            })
            ->join('catalogue_product_variants as variants', function ($join): void {
                $join->on('variants.id', '=', 'balances.product_variant_id')
                    ->on('variants.tenant_id', '=', 'balances.tenant_id');
            })
            ->join('catalogue_products as products', function ($join): void {
                $join->on('products.id', '=', 'variants.product_id')
                    ->on('products.tenant_id', '=', 'variants.tenant_id');
            })
            ->where('balances.tenant_id', $tenantId)
            ->where('warehouses.status', 'active')
            ->where('variants.status', 'active')
            ->where('products.status', 'active')
            ->when($warehouseId !== '', static fn ($builder) => $builder->where('balances.warehouse_id', $warehouseId))
            ->orderBy('warehouses.name')
            ->orderBy('products.name')
            ->orderBy('variants.name')
            ->limit(500);

        $rows = $query->get([
            'balances.id',
            'balances.warehouse_id as warehouseId',
            'warehouses.name as warehouseName',
            'products.name as productName',
            'variants.name as variantName',
            'variants.sku',
            'balances.quantity',
        ]);

        return new JsonResponse(['data' => $rows->map(static fn (object $row): array => [
            'id' => (string) $row->id,
            'warehouseId' => (string) $row->warehouseId,
            'warehouseName' => (string) $row->warehouseName,
            'productName' => (string) $row->productName,
            'variantName' => (string) $row->variantName,
            'sku' => (string) $row->sku,
            'quantity' => (int) $row->quantity,
        ])->values()->all()]);
    }
}
