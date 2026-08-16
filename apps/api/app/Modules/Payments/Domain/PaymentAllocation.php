<?php

declare(strict_types=1);

namespace App\Modules\Payments\Domain;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use LogicException;

final class PaymentAllocation extends Model
{
    use HasUuids;

    protected $fillable = ['tenant_id', 'payment_attempt_id', 'sale_id', 'amount_minor', 'currency_code'];

    protected function casts(): array
    {
        return ['amount_minor' => 'integer'];
    }

    protected static function booted(): void
    {
        self::updating(static fn () => throw new LogicException('Payment allocations are append-only.'));
        self::deleting(static fn () => throw new LogicException('Payment allocations are append-only.'));
    }
}
