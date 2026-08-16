<?php

declare(strict_types=1);

namespace Tests\Feature\Modules\Tenancy;

use App\Modules\Tenancy\Application\TenantScope;
use App\Modules\Tenancy\Domain\Branch;
use App\Modules\Tenancy\Domain\Company;
use App\Modules\Tenancy\Domain\Tenant;
use App\Modules\Tenancy\Domain\Warehouse;
use App\Modules\Tenancy\Application\Http\TenantWorkspaceController;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

final class TenantWorkspaceControllerTest extends TestCase
{
    use RefreshDatabase;

    public function test_overview_contains_only_current_tenant_live_counts(): void
    {
        $tenant = Tenant::query()->create(['name' => 'Tenant A', 'jurisdiction_code' => 'KE', 'status' => 'active']);
        $other = Tenant::query()->create(['name' => 'Tenant B', 'jurisdiction_code' => 'KE', 'status' => 'active']);
        $company = Company::query()->create(['tenant_id' => $tenant->getKey(), 'name' => 'Company A', 'status' => 'active']);
        $branch = Branch::query()->create(['tenant_id' => $tenant->getKey(), 'company_id' => $company->getKey(), 'code' => 'A-01', 'name' => 'Branch A', 'status' => 'active']);
        Warehouse::query()->create(['tenant_id' => $tenant->getKey(), 'branch_id' => $branch->getKey(), 'code' => 'A-WH', 'name' => 'Warehouse A', 'status' => 'active']);
        Company::query()->create(['tenant_id' => $other->getKey(), 'name' => 'Company B', 'status' => 'active']);

        $response = $this->app->make(TenantScope::class)->runFor((string) $tenant->getKey(), fn () => $this->app->make(TenantWorkspaceController::class)->overview());

        self::assertSame(200, $response->getStatusCode());
        self::assertSame([
            'tenantName' => 'Tenant A',
            'metrics' => [
                ['label' => 'Active companies', 'value' => '1'],
                ['label' => 'Active branches', 'value' => '1'],
                ['label' => 'Active warehouses', 'value' => '1'],
            ],
            'activity' => [],
        ], $response->getData(true)['data']);
    }
}
