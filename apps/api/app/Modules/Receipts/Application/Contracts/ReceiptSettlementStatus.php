<?php

declare(strict_types=1);

namespace App\Modules\Receipts\Application\Contracts;

interface ReceiptSettlementStatus
{
    public function isFullyPaid(string $saleId, string $currencyCode, int $grossMinor): bool;
}
