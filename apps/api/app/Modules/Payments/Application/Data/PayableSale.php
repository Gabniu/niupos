<?php

declare(strict_types=1);

namespace App\Modules\Payments\Application\Data;

final readonly class PayableSale
{
    public function __construct(public string $saleId, public string $tenantId, public int $grossMinor, public string $currencyCode) {}
}
