<?php

declare(strict_types=1);

namespace App\Integration;

use App\Modules\Payments\Application\Contracts\SalePaymentLookup;
use App\Modules\Payments\Application\Data\PayableSale;
use App\Modules\Sales\Application\Contracts\FinalizedSaleSnapshotReader;

final readonly class SalesPaymentLookupAdapter implements SalePaymentLookup
{
    public function __construct(private FinalizedSaleSnapshotReader $sales) {}

    public function finalized(string $saleId): PayableSale
    {
        $sale = $this->sales->resolve($saleId);

        return new PayableSale($sale->saleId, $sale->tenantId, $sale->grossMinor, $sale->currencyCode);
    }
}
