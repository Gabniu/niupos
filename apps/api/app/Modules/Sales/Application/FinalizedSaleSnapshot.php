<?php

declare(strict_types=1);

namespace App\Modules\Sales\Application;

use DateTimeImmutable;

final readonly class FinalizedSaleSnapshot
{
    /** @param list<FinalizedSaleLineSnapshot> $lines */
    public function __construct(
        public string $saleId,
        public string $tenantId,
        public string $shiftId,
        public string $registerId,
        public string $warehouseId,
        public string $actorUserId,
        public string $currencyCode,
        public int $netMinor,
        public int $taxMinor,
        public int $grossMinor,
        public DateTimeImmutable $finalizedAt,
        public array $lines,
    ) {}
}
