<?php

declare(strict_types=1);

namespace App\Modules\Identity\Domain;

use Illuminate\Database\Eloquent\Model;

final class Permission extends Model
{
    public $incrementing = false;

    protected $keyType = 'string';

    protected $fillable = ['key', 'description'];
}
