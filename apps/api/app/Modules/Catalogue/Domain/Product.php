<?php

declare(strict_types=1);

namespace App\Modules\Catalogue\Domain;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;

final class Product extends Model
{
    use HasUuids;

    protected $table = 'catalogue_products';

    protected $fillable = ['tenant_id', 'category_id', 'name', 'status'];
}
