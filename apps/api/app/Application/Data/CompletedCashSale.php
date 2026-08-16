<?php

declare(strict_types=1);

namespace App\Application\Data;

final readonly class CompletedCashSale
{
    public function __construct(
        public string $saleId,
        public string $paymentAttemptId,
        public string $cashMovementId,
        public string $receiptId,
        public int $receiptNumber,
        public int $amountMinor,
        public string $currencyCode,
    ) {}
}
