<?php

declare(strict_types=1);

namespace App\Modules\Identity\Domain;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;

final class ApiSession extends Model
{
    use HasUuids;

    protected $fillable = [
        'user_id',
        'token_hash',
        'expires_at',
        'last_used_at',
        'revoked_at',
        'mfa_elevated_until',
    ];

    protected function casts(): array
    {
        return [
            'expires_at' => 'immutable_datetime',
            'last_used_at' => 'immutable_datetime',
            'revoked_at' => 'immutable_datetime',
            'mfa_elevated_until' => 'immutable_datetime',
        ];
    }
}
