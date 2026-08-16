<?php

declare(strict_types=1);

namespace App\Modules\Catalogue\Application\Http;

use App\Modules\Catalogue\Domain\CatalogueStatus;
use App\Modules\Catalogue\Domain\ProductVariant;
use App\Modules\Tenancy\Application\TenantContext;
use Illuminate\Http\JsonResponse;
use Symfony\Component\HttpFoundation\Response;

final readonly class VariantController
{
    public function __construct(private TenantContext $context) {}

    public function show(string $variant): JsonResponse
    {
        $row = ProductVariant::query()
            ->join('catalogue_products', function ($join): void {
                $join->on('catalogue_products.id', '=', 'catalogue_product_variants.product_id')
                    ->on('catalogue_products.tenant_id', '=', 'catalogue_product_variants.tenant_id');
            })
            ->where('catalogue_product_variants.tenant_id', (string) $this->context->id())
            ->where('catalogue_product_variants.status', CatalogueStatus::Active->value)
            ->where('catalogue_products.status', CatalogueStatus::Active->value)
            ->where('catalogue_product_variants.id', $variant)
            ->first([
                'catalogue_product_variants.id',
                'catalogue_product_variants.name as variant_name',
                'catalogue_product_variants.sku',
                'catalogue_products.name as product_name',
            ]);

        if ($row === null) {
            return new JsonResponse(['error' => ['code' => 'CATALOGUE_VARIANT_NOT_FOUND', 'message' => 'The requested product is not available.']], Response::HTTP_NOT_FOUND);
        }

        return new JsonResponse(['data' => [
            'id' => (string) $row->id,
            'name' => (string) $row->product_name,
            'variantName' => (string) $row->variant_name,
            'sku' => (string) $row->sku,
        ]]);
    }
}
