<?php

declare(strict_types=1);

namespace App\Modules\Tenancy\Application\Contracts;

use App\Modules\Tenancy\Domain\Branch;
use App\Modules\Tenancy\Domain\Company;
use App\Modules\Tenancy\Domain\Warehouse;
use Illuminate\Support\Collection;

interface OrganizationLocations
{
    public function createCompany(string $name): Company;

    public function createBranch(string $companyId, string $code, string $name): Branch;

    public function createWarehouse(string $branchId, string $code, string $name): Warehouse;

    /** @return Collection<int, Company> */
    public function companies(): Collection;

    /** @return Collection<int, Branch> */
    public function branches(string $companyId): Collection;

    /** @return Collection<int, Warehouse> */
    public function warehouses(string $branchId): Collection;
}
