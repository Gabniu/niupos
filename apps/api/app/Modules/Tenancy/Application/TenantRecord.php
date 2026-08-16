<?php

declare(strict_types=1);

namespace App\Modules\Tenancy\Application;

final readonly class TenantRecord
{
    public function __construct(
        public string $id,
        public string $name,
        public string $jurisdictionCode,
    ) {}
}
