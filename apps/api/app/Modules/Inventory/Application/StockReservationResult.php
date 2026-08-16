<?php

declare(strict_types=1);

namespace App\Modules\Inventory\Application;

final readonly class StockReservationResult
{
    public function __construct(
        public string $reservationId,
        public string $status,
        public string $warehouseId,
        public string $variantId,
        public int $quantity,
        public ?string $movementId = null,
    ) {}
}
