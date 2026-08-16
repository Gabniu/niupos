<?php

declare(strict_types=1);

namespace App\Modules\Tenancy\Domain;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;

final class Warehouse extends Model
{
    use HasUuids;

    protected $fillable = ['tenant_id', 'branch_id', 'code', 'name', 'status'];
}
