<?php

declare(strict_types=1);

namespace App\Modules\Receipts\Application\Contracts;

use App\Modules\Receipts\Application\IssuedReceipt;
use DateTimeInterface;

interface ReceiptIssuer
{
    public function issue(string $saleId, string $sellerId, string $idempotencyKey, DateTimeInterface $issuedAt): IssuedReceipt;
}
