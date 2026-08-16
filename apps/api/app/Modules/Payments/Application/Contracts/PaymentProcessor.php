<?php

declare(strict_types=1);

namespace App\Modules\Payments\Application\Contracts;

use App\Modules\Payments\Application\Data\PaymentResult;

interface PaymentProcessor
{
    /** @param array<string, scalar|null> $providerMetadata */
    public function initiate(string $saleId, string $method, int $amountMinor, string $currencyCode, string $actorUserId, string $idempotencyKey, array $providerMetadata = []): PaymentResult;

    public function applyProviderResult(string $attemptId, string $status, string $providerReference, string $resultFingerprint): PaymentResult;
}
