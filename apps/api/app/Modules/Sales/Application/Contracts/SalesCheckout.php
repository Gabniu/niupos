<?php

declare(strict_types=1);

namespace App\Modules\Sales\Application\Contracts;

use App\Modules\Sales\Application\FinalizedSale;
use DateTimeInterface;

interface SalesCheckout
{
    /** @param list<array{variant_id: string, quantity: int}> $lines */
    public function finalize(
        string $registerId,
        string $actorUserId,
        string $warehouseId,
        string $priceBookId,
        string $currencyCode,
        array $lines,
        string $idempotencyKey,
        DateTimeInterface $occurredAt,
    ): FinalizedSale;
}
