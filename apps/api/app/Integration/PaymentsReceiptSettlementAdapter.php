<?php

declare(strict_types=1);

namespace App\Integration;

use App\Modules\Payments\Application\Contracts\PaymentSettlementReader;
use App\Modules\Receipts\Application\Contracts\ReceiptSettlementStatus;

final readonly class PaymentsReceiptSettlementAdapter implements ReceiptSettlementStatus
{
    public function __construct(private PaymentSettlementReader $payments) {}

    public function isFullyPaid(string $saleId, string $currencyCode, int $grossMinor): bool
    {
        return $this->payments->isFullyPaid($saleId, $currencyCode, $grossMinor);
    }
}
