<?php

declare(strict_types=1);

namespace App\Modules\Inventory\Domain;

enum StockReservationStatus: string
{
    case Active = 'active';
    case Finalized = 'finalized';
    case Released = 'released';
}
