<?php

declare(strict_types=1);

namespace App\Modules\Sales\Application\Contracts;

use App\Modules\Sales\Application\FinalizedSaleSnapshot;

interface FinalizedSaleSnapshotReader
{
    public function resolve(string $saleId): FinalizedSaleSnapshot;
}
