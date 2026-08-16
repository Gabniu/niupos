<?php

declare(strict_types=1);

namespace App\Modules\Receipts\Application;

use DateTimeImmutable;

final readonly class IssuedReceipt
{
    public function __construct(public string $receiptId, public string $saleId, public string $registerId, public int $receiptNumber, public DateTimeImmutable $issuedAt) {}
}
