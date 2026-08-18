<?php

declare(strict_types=1);

namespace App\Modules\Catalogue\Application\Contracts;

use App\Modules\Catalogue\Application\CreatedCatalogueProduct;
use App\Modules\Catalogue\Domain\Barcode;
use App\Modules\Catalogue\Domain\Product;
use App\Modules\Catalogue\Domain\ProductVariant;

interface CatalogueManager
{
    public function createProductWithDefaultVariant(
        string $name,
        string $sku,
        string $unitOfMeasureId,
        ?string $categoryId = null,
        ?string $barcode = null,
    ): CreatedCatalogueProduct;

    public function resolveBarcode(string $barcode): ?ProductVariant;

    public function updateProduct(string $productId, string $name, ?string $categoryId = null): Product;

    public function updateVariant(string $variantId, string $name, string $sku, string $unitOfMeasureId): ProductVariant;

    public function updateBarcode(string $barcodeId, string $value): Barcode;

    public function deactivateProduct(string $productId): void;

    public function deleteProduct(string $productId): void;
}
