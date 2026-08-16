<?php

declare(strict_types=1);

namespace App\Modules\Receipts\Application;

use DateTimeImmutable;

final readonly class ReceiptSaleSnapshot
{
    /** @param list<ReceiptSaleLineSnapshot> $lines */
    public function __construct(
        public string $tenantId, public string $saleId, public string $shiftId,
        public string $registerId, public string $sellerId, public string $currencyCode,
        public int $netMinor, public int $taxMinor, public int $grossMinor,
        public DateTimeImmutable $finalizedAt, public array $lines,
    ) {}
}
