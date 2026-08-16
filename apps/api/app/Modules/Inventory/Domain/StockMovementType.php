<?php

declare(strict_types=1);

namespace App\Modules\Inventory\Domain;

enum StockMovementType: string
{
    case Receipt = 'receipt';
    case Adjustment = 'adjustment';
    case Sale = 'sale';
}
