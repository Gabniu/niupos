<?php

declare(strict_types=1);

namespace App\Modules\Sales\Domain;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;

final class SaleLine extends Model
{
    use HasUuids;

    public $timestamps = false;

    protected $guarded = [];

    protected static function booted(): void
    {
        self::updating(static fn () => throw new \LogicException('Sale lines are immutable.'));
        self::deleting(static fn () => throw new \LogicException('Sale lines are immutable.'));
    }
}
