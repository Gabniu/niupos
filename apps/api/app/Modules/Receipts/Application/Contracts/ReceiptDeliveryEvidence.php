<?php

declare(strict_types=1);

namespace App\Modules\Receipts\Application\Contracts;

use DateTimeInterface;

interface ReceiptDeliveryEvidence
{
    public function record(string $receiptId, string $channel, string $outcome, DateTimeInterface $attemptedAt, ?string $errorCode = null): string;
}
