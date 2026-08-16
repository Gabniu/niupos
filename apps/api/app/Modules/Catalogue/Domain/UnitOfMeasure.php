<?php

declare(strict_types=1);

namespace App\Modules\Catalogue\Domain;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;

final class UnitOfMeasure extends Model
{
    use HasUuids;

    protected $table = 'catalogue_units_of_measure';

    protected $fillable = ['tenant_id', 'code', 'name', 'status'];
}
