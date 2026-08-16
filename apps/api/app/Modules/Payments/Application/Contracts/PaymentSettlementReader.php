<?php

declare(strict_types=1);

namespace App\Modules\Payments\Application\Contracts;

interface PaymentSettlementReader
{
    public function isFullyPaid(string $saleId, string $currencyCode, int $grossMinor): bool;
}
