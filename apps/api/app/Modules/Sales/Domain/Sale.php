<?php

declare(strict_types=1);

namespace App\Modules\Sales\Domain;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;

final class Sale extends Model
{
    use HasUuids;

    protected $guarded = [];

    protected function casts(): array
    {
        return ['finalized_at' => 'immutable_datetime'];
    }

    protected static function booted(): void
    {
        self::updating(static fn () => throw new \LogicException('Finalized sales are immutable.'));
        self::deleting(static fn () => throw new \LogicException('Finalized sales are immutable.'));
    }
}
