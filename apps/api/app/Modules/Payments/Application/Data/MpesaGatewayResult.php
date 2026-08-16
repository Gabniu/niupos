<?php

declare(strict_types=1);

namespace App\Modules\Payments\Application\Data;

final readonly class MpesaGatewayResult
{
    public function __construct(public string $status, public string $providerReference, public string $resultFingerprint) {}
}
