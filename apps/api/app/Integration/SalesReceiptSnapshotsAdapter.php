<?php

declare(strict_types=1);

namespace App\Integration;

use App\Modules\Receipts\Application\Contracts\ReceiptSaleSnapshots;
use App\Modules\Receipts\Application\ReceiptSaleLineSnapshot;
use App\Modules\Receipts\Application\ReceiptSaleSnapshot;
use App\Modules\Sales\Application\Contracts\FinalizedSaleSnapshotReader;

final readonly class SalesReceiptSnapshotsAdapter implements ReceiptSaleSnapshots
{
    public function __construct(private FinalizedSaleSnapshotReader $sales) {}

    public function finalized(string $saleId): ReceiptSaleSnapshot
    {
        $sale = $this->sales->resolve($saleId);
        $lines = array_map(static fn ($line): ReceiptSaleLineSnapshot => new ReceiptSaleLineSnapshot(
            $line->lineNumber,
            $line->variantId,
            null,
            $line->quantity,
            $line->unitPriceMinor,
            $line->netMinor,
            $line->taxMinor,
            $line->grossMinor,
            $line->taxCode,
            $line->taxRateBasisPoints,
            $line->taxMode === 'inclusive',
        ), $sale->lines);

        return new ReceiptSaleSnapshot(
            $sale->tenantId,
            $sale->saleId,
            $sale->shiftId,
            $sale->registerId,
            $sale->actorUserId,
            $sale->currencyCode,
            $sale->netMinor,
            $sale->taxMinor,
            $sale->grossMinor,
            $sale->finalizedAt,
            $lines,
        );
    }
}
