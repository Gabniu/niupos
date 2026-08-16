<?php

declare(strict_types=1);

namespace App\Modules\Register\Domain;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use LogicException;

final class Device extends Model
{
    use HasUuids;

    protected $fillable = [
        'tenant_id', 'register_id', 'public_id', 'display_name', 'status',
        'enrollment_token_digest', 'enrollment_expires_at', 'enrollment_consumed_at', 'last_seen_at',
    ];

    protected $hidden = ['enrollment_token_digest'];

    protected static function booted(): void
    {
        self::updating(function (self $device): void {
            if ($device->isDirty('public_id')) {
                throw new LogicException('A device public identifier is immutable.');
            }
        });
    }

    protected function casts(): array
    {
        return [
            'enrollment_expires_at' => 'immutable_datetime',
            'enrollment_consumed_at' => 'immutable_datetime',
            'last_seen_at' => 'immutable_datetime',
        ];
    }
}
