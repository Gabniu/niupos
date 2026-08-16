<?php

declare(strict_types=1);

namespace App\Modules\Identity\Domain;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;

final class Role extends Model
{
    use HasUuids;

    protected $fillable = ['tenant_id', 'name', 'description'];
}
