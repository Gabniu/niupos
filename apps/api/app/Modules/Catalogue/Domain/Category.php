<?php

declare(strict_types=1);

namespace App\Modules\Catalogue\Domain;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;

final class Category extends Model
{
    use HasUuids;

    protected $table = 'catalogue_categories';

    protected $fillable = ['tenant_id', 'name', 'status'];
}
