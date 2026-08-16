<?php

declare(strict_types=1);

namespace App\Modules\Catalogue\Application;

use App\Modules\Catalogue\Domain\Barcode;
use App\Modules\Catalogue\Domain\Product;
use App\Modules\Catalogue\Domain\ProductVariant;

final readonly class CreatedCatalogueProduct
{
    public function __construct(
        public Product $product,
        public ProductVariant $defaultVariant,
        public ?Barcode $barcode,
    ) {}
}
