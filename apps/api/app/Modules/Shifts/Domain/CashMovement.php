<?php

declare(strict_types=1);

namespace App\Modules\Shifts\Domain;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use LogicException;

final class CashMovement extends Model
{
    use HasUuids;

    public $timestamps = false;

    protected $fillable = ['tenant_id', 'shift_id', 'type', 'amount_minor', 'currency', 'reason', 'actor_user_id', 'idempotency_key', 'occurred_at'];

    protected static function booted(): void
    {
        self::updating(static function (): never {
            throw new LogicException('Cash movements are append-only.');
        });
        self::deleting(static function (): never {
            throw new LogicException('Cash movements are append-only.');
        });
    }

    protected function casts(): array
    {
        return ['amount_minor' => 'integer', 'occurred_at' => 'immutable_datetime'];
    }
}
