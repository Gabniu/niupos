<?php

declare(strict_types=1);

namespace App\Modules\Inventory\Application\Contracts;

use App\Modules\Inventory\Application\PostedStockMovement;

interface InventoryLedger
{
    public function postReceipt(string $warehouseId, string $variantId, int $quantity, string $idempotencyKey): PostedStockMovement;

    public function postAdjustment(string $warehouseId, string $variantId, int $quantityDelta, string $idempotencyKey): PostedStockMovement;

    public function postSale(string $warehouseId, string $variantId, int $quantity, string $idempotencyKey): PostedStockMovement;

    public function balance(string $warehouseId, string $variantId): int;
}
