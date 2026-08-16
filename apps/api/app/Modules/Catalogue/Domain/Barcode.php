<?php

declare(strict_types=1);

namespace App\Modules\Catalogue\Domain;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;

final class Barcode extends Model
{
    use HasUuids;

    protected $table = 'catalogue_barcodes';

    protected $fillable = ['tenant_id', 'product_variant_id', 'value', 'normalized_value', 'status'];
}
