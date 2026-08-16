<?php

declare(strict_types=1);

namespace App\Modules\Receipts\Domain;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;

final class Receipt extends Model
{
    use HasUuids;

    protected $guarded = [];

    protected function casts(): array
    {
        return ['issued_at' => 'immutable_datetime'];
    }

    protected static function booted(): void
    {
        self::updating(static fn () => throw new \LogicException('Issued receipts are immutable.'));
        self::deleting(static fn () => throw new \LogicException('Issued receipts are immutable.'));
    }
}
