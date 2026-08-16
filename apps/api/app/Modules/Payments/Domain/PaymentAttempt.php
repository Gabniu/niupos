<?php

declare(strict_types=1);

namespace App\Modules\Payments\Domain;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use LogicException;

final class PaymentAttempt extends Model
{
    use HasUuids;

    protected $fillable = ['tenant_id', 'sale_id', 'actor_user_id', 'method', 'status', 'amount_minor', 'currency_code', 'idempotency_key', 'command_fingerprint', 'provider_reference', 'provider_metadata', 'provider_result_fingerprint', 'completed_at'];

    protected function casts(): array
    {
        return ['amount_minor' => 'integer', 'provider_metadata' => 'array', 'completed_at' => 'immutable_datetime'];
    }

    protected static function booted(): void
    {
        self::deleting(static fn () => throw new LogicException('Payment attempts cannot be deleted.'));
        self::updating(function (self $attempt): void {
            $dirty = array_keys($attempt->getDirty());
            $allowed = ['status', 'provider_reference', 'provider_result_fingerprint', 'completed_at', 'updated_at'];
            if ($attempt->getOriginal('status') !== 'pending' || ! in_array($attempt->status, ['succeeded', 'failed'], true) || array_diff($dirty, $allowed) !== []) {
                throw new LogicException('Only a pending payment provider result may transition an attempt.');
            }
        });
    }
}
