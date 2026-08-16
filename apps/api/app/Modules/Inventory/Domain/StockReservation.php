<?php

declare(strict_types=1);

namespace App\Modules\Inventory\Domain;

use Illuminate\Database\Eloquent\Model;
use LogicException;

final class StockReservation extends Model
{
    public $incrementing = false;

    protected $table = 'inventory_stock_reservations';

    protected $keyType = 'string';

    protected $guarded = [];

    protected static function booted(): void
    {
        self::updating(fn () => throw new LogicException('Stock reservations may only transition through the sale-intent service.'));
        self::deleting(fn () => throw new LogicException('Stock reservations are immutable facts.'));
    }

    protected function casts(): array
    {
        return [
            'quantity' => 'integer',
            'status' => StockReservationStatus::class,
            'finalized_at' => 'immutable_datetime',
            'released_at' => 'immutable_datetime',
        ];
    }
}
