<?php

declare(strict_types=1);

namespace App\Modules\Payments\Application;

use App\Modules\Payments\Application\Contracts\PaymentReconciliationReader;
use App\Modules\Payments\Application\Data\PaymentAllocationTotal;
use App\Modules\Payments\Domain\PaymentAllocation;
use App\Modules\Tenancy\Application\TenantContext;
use InvalidArgumentException;

final readonly class DatabasePaymentReconciliationReader implements PaymentReconciliationReader
{
    public function __construct(private TenantContext $tenantContext) {}

    public function totalsForSales(array $saleIds): array
    {
        $saleIds = array_values(array_unique(array_filter(array_map(static fn (mixed $saleId): string => trim((string) $saleId), $saleIds), static fn (string $saleId): bool => $saleId !== '')));
        if (count($saleIds) > 5000) {
            throw new InvalidArgumentException('Payment reconciliation accepts at most 5000 sales per read.');
        }
        if ($saleIds === []) {
            return [];
        }

        return PaymentAllocation::query()
            ->where('tenant_id', (string) $this->tenantContext->id())
            ->whereIn('sale_id', $saleIds)
            ->select('sale_id', 'currency_code')
            ->selectRaw('SUM(amount_minor) as allocated_minor')
            ->groupBy('sale_id', 'currency_code')
            ->orderBy('sale_id')
            ->get()
            ->map(static fn (object $row): PaymentAllocationTotal => new PaymentAllocationTotal(
                (string) $row->sale_id,
                (string) $row->currency_code,
                (int) $row->allocated_minor,
            ))->values()->all();
    }
}
