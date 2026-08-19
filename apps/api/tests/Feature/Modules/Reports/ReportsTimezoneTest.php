<?php

declare(strict_types=1);

namespace Tests\Feature\Modules\Reports;

use App\Modules\Reports\Application\Http\ReportsController;
use App\Modules\Tenancy\Application\TenantScope;
use App\Modules\Tenancy\Domain\Tenant;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

final class ReportsTimezoneTest extends TestCase
{
    use RefreshDatabase;

    public function test_date_only_report_bounds_use_the_tenant_reporting_timezone(): void
    {
        $tenant = Tenant::query()->create(['name' => 'Timezone tenant', 'jurisdiction_code' => 'KE', 'status' => 'active']);
        DB::table('tenant_workspace_preferences')->insert([
            'tenant_id' => $tenant->getKey(),
            'reporting_timezone' => 'Africa/Nairobi',
            'side_panel_visible' => true,
            'kiosk_mode' => false,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $response = $this->app->make(TenantScope::class)->runFor((string) $tenant->getKey(), fn () => $this->app->make(ReportsController::class)->summary(Request::create('/api/v1/reports/summary', 'GET', [
            'from' => '2026-08-01',
            'to' => '2026-08-01',
        ])));

        self::assertSame(200, $response->getStatusCode());
        self::assertSame([
            'from' => '2026-07-31T21:00:00+00:00',
            'to' => '2026-08-01T20:59:59+00:00',
            'timezone' => 'Africa/Nairobi',
        ], $response->getData(true)['data']['period']);
    }

    public function test_reconciliation_is_explicitly_empty_when_no_finalized_sales_exist(): void
    {
        $tenant = Tenant::query()->create(['name' => 'Reconciliation tenant', 'jurisdiction_code' => 'KE', 'status' => 'active']);

        $response = $this->app->make(TenantScope::class)->runFor((string) $tenant->getKey(), fn () => $this->app->make(ReportsController::class)->reconciliation(Request::create('/api/v1/reports/reconciliation', 'GET', [
            'from' => '2026-08-01',
            'to' => '2026-08-31',
        ])));

        self::assertSame(200, $response->getStatusCode());
        self::assertSame('ok', $response->getData(true)['data']['status']);
        self::assertSame(0, $response->getData(true)['data']['checkedSales']);
        self::assertSame([], $response->getData(true)['data']['mismatches']);
    }

    public function test_payment_reconciliation_is_explicitly_empty_when_no_finalized_sales_exist(): void
    {
        $tenant = Tenant::query()->create(['name' => 'Payment reconciliation tenant', 'jurisdiction_code' => 'KE', 'status' => 'active']);

        $response = $this->app->make(TenantScope::class)->runFor((string) $tenant->getKey(), fn () => $this->app->make(ReportsController::class)->paymentReconciliation(Request::create('/api/v1/reports/payment-reconciliation', 'GET', [
            'from' => '2026-08-01',
            'to' => '2026-08-31',
        ])));

        self::assertSame(200, $response->getStatusCode());
        self::assertSame('ok', $response->getData(true)['data']['status']);
        self::assertSame(0, $response->getData(true)['data']['checkedSales']);
        self::assertSame(0, $response->getData(true)['data']['fullyPaidSales']);
        self::assertSame([], $response->getData(true)['data']['mismatches']);
    }
}
