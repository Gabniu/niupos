<?php

declare(strict_types=1);

namespace App\Modules\Inventory\Application;

final readonly class PostedStockMovement
{
    public function __construct(
        public string $movementId,
        public string $type,
        public int $quantityDelta,
        public int $balanceAfter,
    ) {}
}
