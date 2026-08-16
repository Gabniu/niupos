<?php

declare(strict_types=1);

namespace App\Modules\Receipts\Application;

final readonly class ReceiptView
{
    /** @param list<array<string, bool|int|string>> $lines */
    public function __construct(
        public string $id, public string $saleId, public string $shiftId,
        public string $registerId, public string $sellerId, public int $receiptNumber,
        public string $currencyCode, public int $netMinor, public int $taxMinor,
        public int $grossMinor, public string $saleFinalizedAt, public string $issuedAt,
        public array $lines,
    ) {}
}
