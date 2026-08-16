<?php

declare(strict_types=1);

namespace App\Modules\Receipts\Domain;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;

final class ReceiptLine extends Model
{
    use HasUuids;

    public $timestamps = false;

    protected $guarded = [];

    protected function casts(): array
    {
        return ['tax_inclusive' => 'boolean'];
    }

    protected static function booted(): void
    {
        self::updating(static fn () => throw new \LogicException('Receipt lines are immutable.'));
        self::deleting(static fn () => throw new \LogicException('Receipt lines are immutable.'));
    }
}
