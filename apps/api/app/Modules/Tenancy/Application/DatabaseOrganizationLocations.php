<?php

declare(strict_types=1);

namespace App\Modules\Tenancy\Application;

use App\Modules\Tenancy\Application\Contracts\OrganizationLocations;
use App\Modules\Tenancy\Domain\Branch;
use App\Modules\Tenancy\Domain\Company;
use App\Modules\Tenancy\Domain\Warehouse;
use Illuminate\Support\Collection;

final readonly class DatabaseOrganizationLocations implements OrganizationLocations
{
    public function __construct(private TenantContext $context) {}

    public function createCompany(string $name): Company
    {
        return Company::query()->create([
            'tenant_id' => (string) $this->context->id(),
            'name' => $name,
        ]);
    }

    public function createBranch(string $companyId, string $code, string $name): Branch
    {
        $tenantId = (string) $this->context->id();
        Company::query()->where('tenant_id', $tenantId)->findOrFail($companyId);

        return Branch::query()->create([
            'tenant_id' => $tenantId,
            'company_id' => $companyId,
            'code' => $code,
            'name' => $name,
        ]);
    }

    public function createWarehouse(string $branchId, string $code, string $name): Warehouse
    {
        $tenantId = (string) $this->context->id();
        Branch::query()->where('tenant_id', $tenantId)->findOrFail($branchId);

        return Warehouse::query()->create([
            'tenant_id' => $tenantId,
            'branch_id' => $branchId,
            'code' => $code,
            'name' => $name,
        ]);
    }

    public function companies(): Collection
    {
        return Company::query()->where('tenant_id', (string) $this->context->id())->orderBy('name')->get();
    }

    public function branches(string $companyId): Collection
    {
        return Branch::query()
            ->where('tenant_id', (string) $this->context->id())
            ->where('company_id', $companyId)
            ->orderBy('name')
            ->get();
    }

    public function warehouses(string $branchId): Collection
    {
        return Warehouse::query()
            ->where('tenant_id', (string) $this->context->id())
            ->where('branch_id', $branchId)
            ->orderBy('name')
            ->get();
    }
}
