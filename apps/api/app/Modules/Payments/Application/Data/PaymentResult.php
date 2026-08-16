<?php

declare(strict_types=1);

namespace App\Modules\Payments\Application\Data;

final readonly class PaymentResult
{
    public function __construct(public string $attemptId, public string $saleId, public string $method, public string $status, public int $amountMinor, public string $currencyCode, public ?string $providerReference = null) {}
}
