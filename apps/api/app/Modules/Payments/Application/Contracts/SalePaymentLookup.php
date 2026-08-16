<?php

declare(strict_types=1);

namespace App\Modules\Payments\Application\Contracts;

use App\Modules\Payments\Application\Data\PayableSale;

interface SalePaymentLookup
{
    public function finalized(string $saleId): PayableSale;
}
