<?php

declare(strict_types=1);

namespace App\Modules\Catalogue\Application\Contracts;

interface ActiveVariantLookup
{
    public function existsForCurrentTenant(string $variantId): bool;
}
