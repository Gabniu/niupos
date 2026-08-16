<?php

declare(strict_types=1);

namespace App\Modules\Catalogue\Infrastructure;

use App\Modules\Catalogue\Application\Contracts\ActiveVariantLookup;
use App\Modules\Catalogue\Domain\CatalogueStatus;
use App\Modules\Catalogue\Domain\ProductVariant;
use App\Modules\Tenancy\Application\TenantContext;

final readonly class DatabaseActiveVariantLookup implements ActiveVariantLookup
{
    public function __construct(private TenantContext $tenantContext) {}

    public function existsForCurrentTenant(string $variantId): bool
    {
        $tenantId = (string) $this->tenantContext->id();

        return ProductVariant::query()
            ->join('catalogue_products', function ($join): void {
                $join->on('catalogue_products.id', '=', 'catalogue_product_variants.product_id')
                    ->on('catalogue_products.tenant_id', '=', 'catalogue_product_variants.tenant_id');
            })
            ->where('catalogue_product_variants.tenant_id', $tenantId)
            ->where('catalogue_product_variants.id', $variantId)
            ->where('catalogue_product_variants.status', CatalogueStatus::Active->value)
            ->where('catalogue_products.status', CatalogueStatus::Active->value)
            ->exists();
    }
}
