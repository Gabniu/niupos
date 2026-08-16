<?php

declare(strict_types=1);

namespace App\Modules\Inventory\Application\Contracts;

use App\Modules\Inventory\Application\StockReservationResult;

interface InventorySaleIntent
{
    public function available(string $warehouseId, string $variantId): int;

    public function reserve(string $reservationId, string $warehouseId, string $variantId, int $quantity, string $idempotencyKey): StockReservationResult;

    public function finalize(string $reservationId, string $idempotencyKey): StockReservationResult;

    public function release(string $reservationId, string $idempotencyKey): StockReservationResult;
}
