<?php

declare(strict_types=1);

namespace Tests\Feature\Modules\Fiscal;

use App\Modules\Fiscal\Application\Contracts\FiscalSubmissionQueue;
use App\Modules\Fiscal\Application\Data\FiscalInvoice;
use App\Modules\Fiscal\FiscalServiceProvider;
use App\Modules\Identity\Domain\User;
use App\Modules\Register\Domain\Register;
use App\Modules\Sales\Domain\Sale;
use App\Modules\Tenancy\Application\TenantScope;
use App\Modules\Tenancy\Domain\Branch;
use App\Modules\Tenancy\Domain\Company;
use App\Modules\Tenancy\Domain\Tenant;
use App\Modules\Tenancy\Domain\TenantId;
use App\Modules\Tenancy\Domain\Warehouse;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use RuntimeException;
use Tests\TestCase;

final class FiscalSubmissionQueueTest extends TestCase
{
    use RefreshDatabase;

    protected function getPackageProviders($app): array
    {
        return [FiscalServiceProvider::class];
    }

    public function test_enqueue_is_idempotent_and_tenant_scoped(): void
    {
        $first = $this->saleFixture('fiscal-a');
        $second = $this->saleFixture('fiscal-b');
        $invoice = new FiscalInvoice($first['sale'], 'ke.etims', 'KES', 1000, 160, 1160, 'fiscal-sale-a', ['invoiceNumber' => 'local-1']);

        $this->inTenant($first['tenant'], function (FiscalSubmissionQueue $queue) use ($invoice): void {
            $created = $queue->enqueue($invoice);
            $replay = $queue->enqueue($invoice);
            self::assertSame($created->id, $replay->id);
            self::assertSame('queued', $created->status);
            self::assertSame($created->id, $queue->findForSale($invoice->saleId)?->id);
        });

        $this->inTenant($second['tenant'], function (FiscalSubmissionQueue $queue) use ($invoice): void {
            self::assertNull($queue->findForSale($invoice->saleId));
        });
    }

    public function test_conflicting_invoice_for_a_sale_is_rejected(): void
    {
        $fixture = $this->saleFixture('fiscal-conflict');
        $first = new FiscalInvoice($fixture['sale'], 'ke.etims', 'KES', 1000, 160, 1160, 'fiscal-conflict', ['invoiceNumber' => 'local-1']);
        $conflict = new FiscalInvoice($fixture['sale'], 'ke.etims', 'KES', 1000, 180, 1180, 'fiscal-conflict-2', ['invoiceNumber' => 'local-2']);

        $this->inTenant($fixture['tenant'], function (FiscalSubmissionQueue $queue) use ($first, $conflict): void {
            $queue->enqueue($first);
            $this->expectException(RuntimeException::class);
            $queue->enqueue($conflict);
        });
    }

    /** @return array{tenant:string,sale:string} */
    private function saleFixture(string $suffix): array
    {
        $tenant = Tenant::query()->create(['name' => "Fiscal {$suffix}", 'jurisdiction_code' => 'KE', 'status' => 'active']);
        $company = Company::query()->create(['tenant_id' => $tenant->id, 'name' => "Company {$suffix}", 'status' => 'active']);
        $branch = Branch::query()->create(['tenant_id' => $tenant->id, 'company_id' => $company->id, 'code' => "BR-{$suffix}", 'name' => "Branch {$suffix}", 'status' => 'active']);
        $warehouse = Warehouse::query()->create(['tenant_id' => $tenant->id, 'branch_id' => $branch->id, 'code' => "WH-{$suffix}", 'name' => "Warehouse {$suffix}", 'status' => 'active']);
        $register = Register::query()->create(['tenant_id' => $tenant->id, 'branch_id' => $branch->id, 'code' => "POS-{$suffix}", 'name' => "Register {$suffix}", 'status' => 'active']);
        $user = User::factory()->create();
        $shiftId = (string) Str::uuid();
        DB::table('shifts')->insert(['id' => $shiftId, 'tenant_id' => $tenant->id, 'register_id' => $register->id, 'opening_user_id' => $user->id, 'status' => 'open', 'currency' => 'KES', 'opening_float_minor' => 0, 'expected_cash_minor' => 0, 'opened_at' => now(), 'idempotency_key' => "shift-{$suffix}", 'created_at' => now(), 'updated_at' => now()]);
        $sale = Sale::query()->create(['tenant_id' => $tenant->id, 'shift_id' => $shiftId, 'register_id' => $register->id, 'warehouse_id' => $warehouse->id, 'actor_user_id' => $user->id, 'status' => 'finalized', 'currency_code' => 'KES', 'net_minor' => 1000, 'tax_minor' => 160, 'gross_minor' => 1160, 'idempotency_key' => "sale-{$suffix}", 'command_fingerprint' => hash('sha256', $suffix), 'finalized_at' => now()]);

        return ['tenant' => (string) $tenant->id, 'sale' => (string) $sale->id];
    }

    private function inTenant(string $tenantId, callable $operation): mixed
    {
        return $this->app->make(TenantScope::class)->run(TenantId::fromString($tenantId), fn () => $operation($this->app->make(FiscalSubmissionQueue::class)));
    }
}
