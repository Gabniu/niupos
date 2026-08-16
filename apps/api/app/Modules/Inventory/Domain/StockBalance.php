<?php

declare(strict_types=1);

namespace App\Modules\Inventory\Domain;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;

final class StockBalance extends Model
{
    use HasUuids;

    protected $table = 'inventory_stock_balances';

    protected $fillable = ['tenant_id', 'warehouse_id', 'product_variant_id', 'quantity'];

    protected function casts(): array
    {
        return ['quantity' => 'integer'];
    }
}
