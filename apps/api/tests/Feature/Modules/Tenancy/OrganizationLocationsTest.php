<?php

declare(strict_types=1);

namespace Tests\Feature\Modules\Tenancy;

use App\Modules\Tenancy\Application\Contracts\OrganizationLocations;
use App\Modules\Tenancy\Application\TenantScope;
use App\Modules\Tenancy\Domain\Tenant;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use LogicException;
use Tests\TestCase;

final class OrganizationLocationsTest extends TestCase
{
    use RefreshDatabase;

    public function test_context_is_required_and_hierarchy_is_tenant_owned(): void
    {
        $locations = $this->app->make(OrganizationLocations::class);
        $this->expectException(LogicException::class);
        $locations->createCompany('Context-free company');
    }

    public function test_cross_tenant_parent_references_are_rejected(): void
    {
        [$tenantA, $tenantB] = $this->tenants();
        $scope = $this->app->make(TenantScope::class);
        $locations = $this->app->make(OrganizationLocations::class);

        $companyA = $scope->runFor($tenantA, fn () => $locations->createCompany('Company A'));
        $branchA = $scope->runFor($tenantA, fn () => $locations->createBranch($companyA->getKey(), 'A-01', 'Branch A'));

        try {
            $scope->runFor($tenantB, fn () => $locations->createBranch($companyA->getKey(), 'X-01', 'Leaked branch'));
            self::fail('A branch must not reference another tenant company.');
        } catch (ModelNotFoundException) {
            self::assertTrue(true);
        }

        $this->expectException(ModelNotFoundException::class);
        $scope->runFor($tenantB, fn () => $locations->createWarehouse($branchA->getKey(), 'X-WH', 'Leaked warehouse'));
    }

    public function test_reads_are_scoped_to_current_tenant_and_parent(): void
    {
        [$tenantA, $tenantB] = $this->tenants();
        $scope = $this->app->make(TenantScope::class);
        $locations = $this->app->make(OrganizationLocations::class);

        [$companyA, $branchA] = $scope->runFor($tenantA, function () use ($locations): array {
            $company = $locations->createCompany('Company A');
            $branch = $locations->createBranch($company->getKey(), 'A-01', 'Branch A');
            $locations->createWarehouse($branch->getKey(), 'A-WH', 'Warehouse A');

            return [$company, $branch];
        });

        $scope->runFor($tenantB, function () use ($locations): void {
            $company = $locations->createCompany('Company B');
            $branch = $locations->createBranch($company->getKey(), 'B-01', 'Branch B');
            $locations->createWarehouse($branch->getKey(), 'B-WH', 'Warehouse B');
        });

        $scope->runFor($tenantA, function () use ($locations, $companyA, $branchA): void {
            self::assertSame(['Company A'], $locations->companies()->pluck('name')->all());
            self::assertSame(['Branch A'], $locations->branches($companyA->getKey())->pluck('name')->all());
            self::assertSame(['Warehouse A'], $locations->warehouses($branchA->getKey())->pluck('name')->all());
        });

        $scope->runFor($tenantB, function () use ($locations, $companyA, $branchA): void {
            self::assertSame(['Company B'], $locations->companies()->pluck('name')->all());
            self::assertTrue($locations->branches($companyA->getKey())->isEmpty());
            self::assertTrue($locations->warehouses($branchA->getKey())->isEmpty());
        });
    }

    /** @return array{string, string} */
    private function tenants(): array
    {
        $create = static fn (string $name): string => (string) Tenant::query()->create([
            'name' => $name,
            'jurisdiction_code' => 'KE',
            'status' => 'active',
        ])->getKey();

        return [$create('Tenant A'), $create('Tenant B')];
    }
}
