<?php

declare(strict_types=1);

namespace App\Modules\Payments\Application\Data;

final readonly class MpesaGatewayRequest
{
    public function __construct(public string $attemptId, public string $saleId, public int $amountMinor, public string $currencyCode, public string $customerReference) {}
}
