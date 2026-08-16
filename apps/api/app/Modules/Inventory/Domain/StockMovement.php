<?php

declare(strict_types=1);

namespace App\Modules\Inventory\Domain;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use LogicException;

final class StockMovement extends Model
{
    use HasUuids;

    protected $table = 'inventory_stock_movements';

    protected $fillable = ['tenant_id', 'warehouse_id', 'product_variant_id', 'movement_type', 'quantity_delta', 'balance_after', 'idempotency_key', 'command_hash'];

    protected static function booted(): void
    {
        self::updating(fn () => throw new LogicException('Stock movements are append-only.'));
        self::deleting(fn () => throw new LogicException('Stock movements are append-only.'));
    }

    protected function casts(): array
    {
        return ['movement_type' => StockMovementType::class, 'quantity_delta' => 'integer', 'balance_after' => 'integer'];
    }
}
