<?php

declare(strict_types=1);

namespace Tests\Feature\Modules\Tenancy;

use App\Modules\Register\Domain\Register;
use App\Modules\Tenancy\Application\Http\WorkspaceLocationsController;
use App\Modules\Tenancy\Application\TenantScope;
use App\Modules\Tenancy\Domain\Branch;
use App\Modules\Tenancy\Domain\Company;
use App\Modules\Tenancy\Domain\Tenant;
use App\Modules\Tenancy\Domain\Warehouse;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

final class WorkspaceLocationsControllerTest extends TestCase
{
    use RefreshDatabase;

    public function test_locations_are_nested_and_tenant_scoped(): void
    {
        $tenant = Tenant::query()->create(['name' => 'Tenant A', 'jurisdiction_code' => 'KE', 'status' => 'active']);
        $other = Tenant::query()->create(['name' => 'Tenant B', 'jurisdiction_code' => 'KE', 'status' => 'active']);
        $company = Company::query()->create(['tenant_id' => $tenant->getKey(), 'name' => 'Company A', 'status' => 'active']);
        $branch = Branch::query()->create(['tenant_id' => $tenant->getKey(), 'company_id' => $company->getKey(), 'code' => 'A-01', 'name' => 'Branch A', 'status' => 'active']);
        $warehouse = Warehouse::query()->create(['tenant_id' => $tenant->getKey(), 'branch_id' => $branch->getKey(), 'code' => 'A-WH', 'name' => 'Warehouse A', 'status' => 'active']);
        $register = Register::query()->create(['tenant_id' => $tenant->getKey(), 'branch_id' => $branch->getKey(), 'code' => 'A-REG', 'name' => 'Register A', 'status' => 'active']);
        Company::query()->create(['tenant_id' => $other->getKey(), 'name' => 'Company B', 'status' => 'active']);

        $response = $this->app->make(TenantScope::class)->runFor((string) $tenant->getKey(), fn () => $this->app->make(WorkspaceLocationsController::class)->index());
        $data = $response->getData(true)['data'];

        self::assertSame(200, $response->getStatusCode());
        self::assertSame((string) $branch->getKey(), $data[0]['id']);
        self::assertSame((string) $warehouse->getKey(), $data[0]['warehouses'][0]['id']);
        self::assertSame((string) $register->getKey(), $data[0]['registers'][0]['id']);
        self::assertCount(1, $data);
    }
}
