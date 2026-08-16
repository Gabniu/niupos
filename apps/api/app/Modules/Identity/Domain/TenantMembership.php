<?php

declare(strict_types=1);

namespace App\Modules\Identity\Domain;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;

final class TenantMembership extends Model
{
    use HasUuids;

    protected $fillable = ['tenant_id', 'user_id', 'role_id', 'status', 'is_owner'];

    protected function casts(): array
    {
        return ['is_owner' => 'boolean'];
    }
}
