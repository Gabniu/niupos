<?php

declare(strict_types=1);

namespace App\Modules\Tenancy\Domain;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;

final class Tenant extends Model
{
    use HasUuids;

    protected $fillable = ['name', 'jurisdiction_code', 'status'];
}
