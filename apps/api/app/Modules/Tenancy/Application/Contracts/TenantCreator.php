<?php

declare(strict_types=1);

namespace App\Modules\Tenancy\Application\Contracts;

use App\Modules\Tenancy\Application\TenantRecord;

interface TenantCreator
{
    public function create(string $name, string $jurisdictionCode): TenantRecord;
}
