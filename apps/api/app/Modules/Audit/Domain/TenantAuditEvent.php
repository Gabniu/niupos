<?php

declare(strict_types=1);

namespace App\Modules\Audit\Domain;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;

final class TenantAuditEvent extends Model
{
    use HasUuids;

    public $timestamps = false;

    protected $fillable = ['tenant_id', 'event_type', 'actor_user_id', 'metadata', 'occurred_at'];

    protected function casts(): array
    {
        return ['metadata' => 'array', 'occurred_at' => 'immutable_datetime'];
    }
}
