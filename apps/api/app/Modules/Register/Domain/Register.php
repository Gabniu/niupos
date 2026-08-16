<?php

declare(strict_types=1);

namespace App\Modules\Register\Domain;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;

final class Register extends Model
{
    use HasUuids;

    protected $table = 'registers';

    protected $fillable = ['tenant_id', 'branch_id', 'code', 'name', 'status'];
}
