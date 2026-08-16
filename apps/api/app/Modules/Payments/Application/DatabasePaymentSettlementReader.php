<?php

declare(strict_types=1);

namespace App\Modules\Payments\Application;

use App\Modules\Payments\Application\Contracts\PaymentSettlementReader;
use App\Modules\Payments\Domain\PaymentAllocation;
use App\Modules\Tenancy\Application\TenantContext;
use InvalidArgumentException;

final readonly class DatabasePaymentSettlementReader implements PaymentSettlementReader
{
    public function __construct(private TenantContext $tenants) {}

    public function isFullyPaid(string $saleId, string $currencyCode, int $grossMinor): bool
    {
        $currency = strtoupper(trim($currencyCode));
        if ($saleId === '' || $grossMinor <= 0 || ! preg_match('/^[A-Z]{3}$/', $currency)) {
            throw new InvalidArgumentException('A sale, positive gross amount and currency are required.');
        }

        $allocated = (int) PaymentAllocation::query()
            ->where('tenant_id', (string) $this->tenants->id())
            ->where('sale_id', $saleId)
            ->where('currency_code', $currency)
            ->sum('amount_minor');

        return $allocated === $grossMinor;
    }
}
