<?php

declare(strict_types=1);

namespace App\Modules\Receipts\Application\Contracts;

use App\Modules\Receipts\Application\ReceiptSaleSnapshot;

interface ReceiptSaleSnapshots
{
    public function finalized(string $saleId): ReceiptSaleSnapshot;
}
