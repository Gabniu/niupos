<?php

declare(strict_types=1);

namespace App\Modules\Fiscal\Application\Data;

use InvalidArgumentException;

final readonly class FiscalInvoice
{
    /** @param array<string, mixed> $payload */
    public function __construct(
        public string $saleId,
        public string $profile,
        public string $currencyCode,
        public int $netMinor,
        public int $taxMinor,
        public int $grossMinor,
        public string $idempotencyKey,
        public array $payload,
    ) {
        $currency = strtoupper(trim($this->currencyCode));
        if ($this->saleId === '' || $this->profile === '' || strlen($this->profile) > 64 || $this->idempotencyKey === '' || strlen($this->idempotencyKey) > 128 || ! preg_match('/^[A-Z]{3}$/', $currency)) {
            throw new InvalidArgumentException('Fiscal invoice identity is invalid.');
        }
        if ($this->netMinor < 0 || $this->taxMinor < 0 || $this->grossMinor <= 0 || $this->grossMinor < $this->netMinor) {
            throw new InvalidArgumentException('Fiscal invoice amounts are invalid.');
        }
    }

    public function normalizedCurrency(): string
    {
        return strtoupper(trim($this->currencyCode));
    }
}
