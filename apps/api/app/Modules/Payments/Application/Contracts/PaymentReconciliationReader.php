<?php

declare(strict_types=1);

namespace App\Modules\Payments\Application\Contracts;

use App\Modules\Payments\Application\Data\PaymentAllocationTotal;

interface PaymentReconciliationReader
{
    /** @param list<string> $saleIds @return list<PaymentAllocationTotal> */
    public function totalsForSales(array $saleIds): array;
}
