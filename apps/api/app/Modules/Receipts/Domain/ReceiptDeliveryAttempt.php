<?php

declare(strict_types=1);

namespace App\Modules\Receipts\Domain;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;

final class ReceiptDeliveryAttempt extends Model
{
    use HasUuids;

    public $timestamps = false;

    protected $guarded = [];

    protected function casts(): array
    {
        return ['attempted_at' => 'immutable_datetime'];
    }

    protected static function booted(): void
    {
        self::updating(static fn () => throw new \LogicException('Delivery evidence is append-only.'));
        self::deleting(static fn () => throw new \LogicException('Delivery evidence is append-only.'));
    }
}
