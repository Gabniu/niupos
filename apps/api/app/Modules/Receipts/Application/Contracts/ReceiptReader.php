<?php

declare(strict_types=1);

namespace App\Modules\Receipts\Application\Contracts;

use App\Modules\Receipts\Application\ReceiptView;

interface ReceiptReader
{
    public function find(string $receiptId): ?ReceiptView;
}
