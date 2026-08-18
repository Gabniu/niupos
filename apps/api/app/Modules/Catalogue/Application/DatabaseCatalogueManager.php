<?php

declare(strict_types=1);

namespace App\Modules\Catalogue\Application;

use App\Modules\Catalogue\Application\Contracts\CatalogueManager;
use App\Modules\Catalogue\Domain\Barcode;
use App\Modules\Catalogue\Domain\CatalogueStatus;
use App\Modules\Catalogue\Domain\Category;
use App\Modules\Catalogue\Domain\Product;
use App\Modules\Catalogue\Domain\ProductVariant;
use App\Modules\Catalogue\Domain\UnitOfMeasure;
use App\Modules\Sync\Application\Contracts\SyncChangePublisher;
use App\Modules\Tenancy\Application\TenantContext;
use DomainException;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

final readonly class DatabaseCatalogueManager implements CatalogueManager
{
    public function __construct(private TenantContext $tenantContext, private SyncChangePublisher $sync) {}

    public function createProductWithDefaultVariant(string $name, string $sku, string $unitOfMeasureId, ?string $categoryId = null, ?string $barcode = null): CreatedCatalogueProduct
    {
        $tenantId = (string) $this->tenantContext->id();
        $name = trim($name);
        $normalizedSku = self::normalizeSku($sku);
        $normalizedBarcode = $barcode === null ? null : self::normalizeBarcode($barcode);
        if ($name === '') {
            throw new InvalidArgumentException('Product name cannot be empty.');
        }
        if (! UnitOfMeasure::query()->where('tenant_id', $tenantId)->where('status', CatalogueStatus::Active->value)->whereKey($unitOfMeasureId)->exists()) {
            throw new DomainException('Unit of measure must be active and belong to the current tenant.');
        }
        if ($categoryId !== null && ! Category::query()->where('tenant_id', $tenantId)->where('status', CatalogueStatus::Active->value)->whereKey($categoryId)->exists()) {
            throw new DomainException('Category must be active and belong to the current tenant.');
        }

        return DB::transaction(function () use ($tenantId, $name, $sku, $normalizedSku, $unitOfMeasureId, $categoryId, $barcode, $normalizedBarcode): CreatedCatalogueProduct {
            $product = Product::query()->create(['tenant_id' => $tenantId, 'category_id' => $categoryId, 'name' => $name, 'status' => CatalogueStatus::Active->value]);
            $variant = ProductVariant::query()->create([
                'tenant_id' => $tenantId, 'product_id' => $product->getKey(), 'unit_of_measure_id' => $unitOfMeasureId,
                'name' => $name, 'sku' => trim($sku), 'normalized_sku' => $normalizedSku, 'status' => CatalogueStatus::Active->value,
            ]);
            $barcodeModel = $normalizedBarcode === null ? null : Barcode::query()->create([
                'tenant_id' => $tenantId, 'product_variant_id' => $variant->getKey(), 'value' => trim((string) $barcode),
                'normalized_value' => $normalizedBarcode, 'status' => CatalogueStatus::Active->value,
            ]);

            $this->sync->publishChange('catalogue.products', (string) $product->getKey(), 'upsert', [
                'id' => (string) $product->getKey(), 'name' => $product->name, 'categoryId' => $product->category_id,
                'status' => $product->status,
            ]);
            $this->sync->publishChange('catalogue.variants', (string) $variant->getKey(), 'upsert', [
                'id' => (string) $variant->getKey(), 'productId' => (string) $product->getKey(), 'name' => $variant->name,
                'sku' => $variant->sku, 'unitOfMeasureId' => (string) $variant->unit_of_measure_id, 'status' => $variant->status,
            ]);
            if ($barcodeModel !== null) {
                $this->sync->publishChange('catalogue.barcodes', (string) $barcodeModel->getKey(), 'upsert', [
                    'id' => (string) $barcodeModel->getKey(), 'variantId' => (string) $variant->getKey(),
                    'value' => $barcodeModel->value, 'status' => $barcodeModel->status,
                ]);
            }

            return new CreatedCatalogueProduct($product, $variant, $barcodeModel);
        });
    }

    public function resolveBarcode(string $barcode): ?ProductVariant
    {
        $tenantId = (string) $this->tenantContext->id();
        $identity = Barcode::query()->where('tenant_id', $tenantId)
            ->where('normalized_value', self::normalizeBarcode($barcode))
            ->where('status', CatalogueStatus::Active->value)->first();
        if ($identity === null) {
            return null;
        }

        return ProductVariant::query()->where('tenant_id', $tenantId)->where('status', CatalogueStatus::Active->value)->find($identity->product_variant_id);
    }

    public function updateProduct(string $productId, string $name, ?string $categoryId = null): Product
    {
        $tenantId = (string) $this->tenantContext->id();
        $name = trim($name);
        if ($name === '') {
            throw new InvalidArgumentException('Product name cannot be empty.');
        }
        if ($categoryId !== null && ! Category::query()->where('tenant_id', $tenantId)->where('status', CatalogueStatus::Active->value)->whereKey($categoryId)->exists()) {
            throw new DomainException('Category must be active and belong to the current tenant.');
        }

        return DB::transaction(function () use ($tenantId, $productId, $name, $categoryId): Product {
            $product = Product::query()->where('tenant_id', $tenantId)->whereKey($productId)->lockForUpdate()->first();
            if ($product === null) {
                throw new DomainException('Product must belong to the current tenant.');
            }
            $product->update(['name' => $name, 'category_id' => $categoryId]);
            $this->sync->publishChange('catalogue.products', (string) $product->getKey(), 'upsert', [
                'id' => (string) $product->getKey(), 'name' => $product->name, 'categoryId' => $product->category_id,
                'status' => $product->status,
            ]);

            return $product->refresh();
        });
    }

    public function updateVariant(string $variantId, string $name, string $sku, string $unitOfMeasureId): ProductVariant
    {
        $tenantId = (string) $this->tenantContext->id();
        $name = trim($name);
        $normalizedSku = self::normalizeSku($sku);
        if ($name === '') {
            throw new InvalidArgumentException('Variant name cannot be empty.');
        }
        if (! UnitOfMeasure::query()->where('tenant_id', $tenantId)->where('status', CatalogueStatus::Active->value)->whereKey($unitOfMeasureId)->exists()) {
            throw new DomainException('Unit of measure must be active and belong to the current tenant.');
        }

        return DB::transaction(function () use ($tenantId, $variantId, $name, $sku, $normalizedSku, $unitOfMeasureId): ProductVariant {
            $variant = ProductVariant::query()->where('tenant_id', $tenantId)->whereKey($variantId)->lockForUpdate()->first();
            if ($variant === null) {
                throw new DomainException('Variant must belong to the current tenant.');
            }
            $variant->update(['name' => $name, 'sku' => trim($sku), 'normalized_sku' => $normalizedSku, 'unit_of_measure_id' => $unitOfMeasureId]);
            $this->sync->publishChange('catalogue.variants', (string) $variant->getKey(), 'upsert', [
                'id' => (string) $variant->getKey(), 'productId' => (string) $variant->product_id, 'name' => $variant->name,
                'sku' => $variant->sku, 'unitOfMeasureId' => (string) $variant->unit_of_measure_id, 'status' => $variant->status,
            ]);

            return $variant->refresh();
        });
    }

    public function updateBarcode(string $barcodeId, string $value): Barcode
    {
        $tenantId = (string) $this->tenantContext->id();
        $normalizedValue = self::normalizeBarcode($value);

        return DB::transaction(function () use ($tenantId, $barcodeId, $value, $normalizedValue): Barcode {
            $barcode = Barcode::query()->where('tenant_id', $tenantId)->whereKey($barcodeId)->lockForUpdate()->first();
            if ($barcode === null) {
                throw new DomainException('Barcode must belong to the current tenant.');
            }
            if (! ProductVariant::query()->where('tenant_id', $tenantId)->where('status', CatalogueStatus::Active->value)->whereKey($barcode->product_variant_id)->exists()) {
                throw new DomainException('Barcode variant must be active and belong to the current tenant.');
            }
            $barcode->update(['value' => trim($value), 'normalized_value' => $normalizedValue]);
            $this->sync->publishChange('catalogue.barcodes', (string) $barcode->getKey(), 'upsert', [
                'id' => (string) $barcode->getKey(), 'variantId' => (string) $barcode->product_variant_id,
                'value' => $barcode->value, 'status' => $barcode->status,
            ]);

            return $barcode->refresh();
        });
    }

    public function deactivateProduct(string $productId): void
    {
        $tenantId = (string) $this->tenantContext->id();
        DB::transaction(function () use ($tenantId, $productId): void {
            $product = Product::query()->where('tenant_id', $tenantId)->whereKey($productId)->lockForUpdate()->first();
            if ($product === null) {
                throw new DomainException('Product must belong to the current tenant.');
            }
            $product->update(['status' => CatalogueStatus::Inactive->value]);
            $this->sync->publishChange('catalogue.products', (string) $product->getKey(), 'upsert', [
                'id' => (string) $product->getKey(), 'name' => $product->name, 'categoryId' => $product->category_id,
                'status' => $product->status,
            ]);
            $variants = ProductVariant::query()->where('tenant_id', $tenantId)->where('product_id', $product->getKey())->lockForUpdate()->get();
            foreach ($variants as $variant) {
                $variant->update(['status' => CatalogueStatus::Inactive->value]);
                $this->sync->publishChange('catalogue.variants', (string) $variant->getKey(), 'upsert', [
                    'id' => (string) $variant->getKey(), 'productId' => (string) $product->getKey(), 'name' => $variant->name,
                    'sku' => $variant->sku, 'unitOfMeasureId' => (string) $variant->unit_of_measure_id, 'status' => $variant->status,
                ]);
                $barcodes = Barcode::query()->where('tenant_id', $tenantId)->where('product_variant_id', $variant->getKey())->lockForUpdate()->get();
                foreach ($barcodes as $barcode) {
                    $barcode->update(['status' => CatalogueStatus::Inactive->value]);
                    $this->sync->publishChange('catalogue.barcodes', (string) $barcode->getKey(), 'upsert', [
                        'id' => (string) $barcode->getKey(), 'variantId' => (string) $variant->getKey(),
                        'value' => $barcode->value, 'status' => $barcode->status,
                    ]);
                }
            }
        });
    }

    public function deleteProduct(string $productId): void
    {
        $tenantId = (string) $this->tenantContext->id();
        DB::transaction(function () use ($tenantId, $productId): void {
            $product = Product::query()->where('tenant_id', $tenantId)->whereKey($productId)->lockForUpdate()->first();
            if ($product === null) {
                throw new DomainException('Product must belong to the current tenant.');
            }
            $variants = ProductVariant::query()->where('tenant_id', $tenantId)->where('product_id', $product->getKey())->lockForUpdate()->get();
            foreach ($variants as $variant) {
                $barcodes = Barcode::query()->where('tenant_id', $tenantId)->where('product_variant_id', $variant->getKey())->lockForUpdate()->get();
                foreach ($barcodes as $barcode) {
                    $this->sync->publishChange('catalogue.barcodes', (string) $barcode->getKey(), 'delete', ['id' => (string) $barcode->getKey()]);
                }
                $this->sync->publishChange('catalogue.variants', (string) $variant->getKey(), 'delete', ['id' => (string) $variant->getKey()]);
            }
            $this->sync->publishChange('catalogue.products', (string) $product->getKey(), 'delete', ['id' => (string) $product->getKey()]);
            $product->delete();
        });
    }

    private static function normalizeSku(string $sku): string
    {
        $normalized = mb_strtoupper(preg_replace('/\s+/', '', trim($sku)) ?? '');
        if ($normalized === '') {
            throw new InvalidArgumentException('SKU cannot be empty.');
        }

        return $normalized;
    }

    private static function normalizeBarcode(string $barcode): string
    {
        $normalized = preg_replace('/\s+/', '', trim($barcode)) ?? '';
        if ($normalized === '') {
            throw new InvalidArgumentException('Barcode cannot be empty.');
        }

        return $normalized;
    }
}
