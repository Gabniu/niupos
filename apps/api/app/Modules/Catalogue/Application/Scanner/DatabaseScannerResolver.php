<?php

declare(strict_types=1);

namespace App\Modules\Catalogue\Application\Scanner;

use App\Modules\Catalogue\Application\Contracts\ScannerResolver;
use App\Modules\Catalogue\Domain\Barcode;
use App\Modules\Catalogue\Domain\CatalogueStatus;
use App\Modules\Catalogue\Domain\ProductVariant;
use App\Modules\Tenancy\Application\TenantContext;
use InvalidArgumentException;

final readonly class DatabaseScannerResolver implements ScannerResolver
{
    public function __construct(private TenantContext $tenantContext) {}

    public function resolve(string $value, ScannerInputMode $mode): ScanResult
    {
        $normalized = self::normalize($value);
        $weighted = WeightedEan::parse($normalized);
        $lookup = $weighted?->itemReference ?? $normalized;
        $tenantId = (string) $this->tenantContext->id();

        $barcode = Barcode::query()
            ->where('tenant_id', $tenantId)
            ->where('normalized_value', $lookup)
            ->where('status', CatalogueStatus::Active->value)
            ->first();

        if ($barcode === null) {
            return ScanResult::unknown($normalized, $weighted);
        }

        $variant = ProductVariant::query()
            ->where('tenant_id', $tenantId)
            ->where('status', CatalogueStatus::Active->value)
            ->find($barcode->product_variant_id);

        return $variant === null
            ? ScanResult::unknown($normalized, $weighted)
            : ScanResult::found($normalized, (string) $variant->getKey(), $weighted);
    }

    private static function normalize(string $value): string
    {
        $normalized = preg_replace('/\s+/', '', trim($value)) ?? '';
        if ($normalized === '') {
            throw new InvalidArgumentException('Scanner value cannot be empty.');
        }

        return $normalized;
    }
}
