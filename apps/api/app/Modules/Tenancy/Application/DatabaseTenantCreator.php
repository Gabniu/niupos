<?php

declare(strict_types=1);

namespace App\Modules\Tenancy\Application;

use App\Modules\Tenancy\Application\Contracts\TenantCreator;
use App\Modules\Tenancy\Domain\Tenant;
use DomainException;

final readonly class DatabaseTenantCreator implements TenantCreator
{
    public function create(string $name, string $jurisdictionCode): TenantRecord
    {
        $name = trim($name);
        $jurisdictionCode = strtoupper(trim($jurisdictionCode));

        if ($name === '' || mb_strlen($name) > 160) {
            throw new DomainException('A valid organization name is required.');
        }
        if (preg_match('/^[A-Z]{2}$/', $jurisdictionCode) !== 1) {
            throw new DomainException('A valid two-letter jurisdiction code is required.');
        }

        $tenant = Tenant::query()->create([
            'name' => $name,
            'jurisdiction_code' => $jurisdictionCode,
            'status' => 'active',
        ]);

        return new TenantRecord((string) $tenant->getKey(), (string) $tenant->name, (string) $tenant->jurisdiction_code);
    }
}
