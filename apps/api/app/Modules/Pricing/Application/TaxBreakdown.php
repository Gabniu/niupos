<?php

declare(strict_types=1);

namespace App\Modules\Pricing\Application;

final readonly class TaxBreakdown
{
    public function __construct(
        public int $netMinor,
        public int $taxMinor,
        public int $grossMinor,
    ) {}
}
