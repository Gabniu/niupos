<?php

declare(strict_types=1);

namespace App\Modules\Payments\Application\Data;

final readonly class PaymentAllocationTotal
{
    public function __construct(
        public string $saleId,
        public string $currencyCode,
        public int $allocatedMinor,
    ) {}
}
