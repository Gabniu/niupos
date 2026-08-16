<?php

declare(strict_types=1);

namespace App\Modules\Catalogue\Domain;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;

final class ProductVariant extends Model
{
    use HasUuids;

    protected $table = 'catalogue_product_variants';

    protected $fillable = ['tenant_id', 'product_id', 'unit_of_measure_id', 'name', 'sku', 'normalized_sku', 'status'];
}
