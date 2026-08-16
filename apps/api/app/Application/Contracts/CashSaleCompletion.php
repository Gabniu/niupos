<?php

declare(strict_types=1);

namespace App\Application\Contracts;

use App\Application\Data\CompletedCashSale;
use DateTimeInterface;

interface CashSaleCompletion
{
    public function complete(string $saleId, string $actorUserId, string $idempotencyKey, DateTimeInterface $completedAt): CompletedCashSale;
}
