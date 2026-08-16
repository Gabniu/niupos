<?php

declare(strict_types=1);

namespace App\Modules\Shifts\Domain;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;

final class Shift extends Model
{
    use HasUuids;

    protected $fillable = [
        'tenant_id', 'register_id', 'opening_user_id', 'closing_user_id', 'status',
        'currency', 'opening_float_minor', 'expected_cash_minor', 'counted_cash_minor',
        'variance_minor', 'opened_at', 'closed_at', 'idempotency_key',
    ];

    protected function casts(): array
    {
        return [
            'opening_float_minor' => 'integer',
            'expected_cash_minor' => 'integer',
            'counted_cash_minor' => 'integer',
            'variance_minor' => 'integer',
            'opened_at' => 'immutable_datetime',
            'closed_at' => 'immutable_datetime',
        ];
    }
}
