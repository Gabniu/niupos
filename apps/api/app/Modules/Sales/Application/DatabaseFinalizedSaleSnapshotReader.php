<?php

declare(strict_types=1);

namespace App\Modules\Sales\Application;

use App\Modules\Sales\Application\Contracts\FinalizedSaleSnapshotReader;
use App\Modules\Sales\Domain\Sale;
use App\Modules\Sales\Domain\SaleLine;
use App\Modules\Tenancy\Application\TenantContext;
use DateTimeImmutable;
use RuntimeException;

final readonly class DatabaseFinalizedSaleSnapshotReader implements FinalizedSaleSnapshotReader
{
    public function __construct(private TenantContext $tenants) {}

    public function resolve(string $saleId): FinalizedSaleSnapshot
    {
        $tenantId = (string) $this->tenants->id();
        $sale = Sale::query()
            ->where('tenant_id', $tenantId)
            ->whereKey($saleId)
            ->where('status', 'finalized')
            ->first();

        if ($sale === null) {
            throw new RuntimeException('Finalized sale was not found.');
        }

        $lines = SaleLine::query()
            ->where('tenant_id', $tenantId)
            ->where('sale_id', $saleId)
            ->orderBy('line_number')
            ->get()
            ->map(static fn (SaleLine $line): FinalizedSaleLineSnapshot => new FinalizedSaleLineSnapshot(
                (int) $line->line_number,
                (string) $line->variant_id,
                (int) $line->quantity,
                (int) $line->unit_price_minor,
                (int) $line->net_minor,
                (int) $line->tax_minor,
                (int) $line->gross_minor,
                (string) $line->tax_code,
                (int) $line->tax_rate_basis_points,
                (bool) $line->tax_inclusive ? 'inclusive' : 'exclusive',
            ))->all();

        return new FinalizedSaleSnapshot(
            (string) $sale->id,
            (string) $sale->tenant_id,
            (string) $sale->shift_id,
            (string) $sale->register_id,
            (string) $sale->warehouse_id,
            (string) $sale->actor_user_id,
            (string) $sale->currency_code,
            (int) $sale->net_minor,
            (int) $sale->tax_minor,
            (int) $sale->gross_minor,
            DateTimeImmutable::createFromInterface($sale->finalized_at),
            $lines,
        );
    }
}
