<?php

declare(strict_types=1);

namespace Tests\Feature\Modules\Receipts;

use App\Modules\Identity\Domain\User;
use App\Modules\Receipts\Application\Contracts\ReceiptDeliveryEvidence;
use App\Modules\Receipts\Application\Contracts\ReceiptIssuer;
use App\Modules\Receipts\Application\Contracts\ReceiptSaleSnapshots;
use App\Modules\Receipts\Application\Contracts\ReceiptSettlementStatus;
use App\Modules\Receipts\Application\ReceiptSaleLineSnapshot;
use App\Modules\Receipts\Application\ReceiptSaleSnapshot;
use App\Modules\Receipts\Domain\Receipt;
use App\Modules\Receipts\Domain\ReceiptDeliveryAttempt;
use App\Modules\Receipts\Domain\ReceiptLine;
use App\Modules\Receipts\ReceiptsServiceProvider;
use App\Modules\Register\Domain\Register;
use App\Modules\Tenancy\Application\TenantScope;
use App\Modules\Tenancy\Domain\Branch;
use App\Modules\Tenancy\Domain\Company;
use App\Modules\Tenancy\Domain\Tenant;
use App\Modules\Tenancy\Domain\TenantId;
use App\Modules\Tenancy\Domain\Warehouse;
use DateTimeImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Mockery;
use RuntimeException;
use Tests\TestCase;

final class ReceiptIssuerTest extends TestCase
{
    use RefreshDatabase;

    protected function getPackageProviders($app): array
    {
        return [ReceiptsServiceProvider::class];
    }

    public function test_issue_snapshots_without_recalculation_numbers_per_register_and_replays_exactly_once(): void
    {
        $f = $this->fixture('one');
        $at = new DateTimeImmutable('2026-08-08T15:00:00+03:00');
        $snapshot = $this->snapshot($f, $at, null);
        $sales = Mockery::mock(ReceiptSaleSnapshots::class);
        $sales->shouldReceive('finalized')->times(3)->with($f['sale'])->andReturn($snapshot);
        $paid = Mockery::mock(ReceiptSettlementStatus::class);
        $paid->shouldReceive('isFullyPaid')->times(3)->with($f['sale'], 'KES', 11600)->andReturnTrue();
        $this->app->instance(ReceiptSaleSnapshots::class, $sales);
        $this->app->instance(ReceiptSettlementStatus::class, $paid);

        $this->inTenant($f['tenant'], function () use ($f, $at): void {
            $issuer = $this->app->make(ReceiptIssuer::class);
            $first = $issuer->issue($f['sale'], $f['user'], 'receipt-one', $at);
            $again = $issuer->issue($f['sale'], $f['user'], 'receipt-one', $at);
            self::assertSame($first->receiptId, $again->receiptId);
            self::assertSame(1, $first->receiptNumber);
            self::assertSame(['net_minor' => 10000, 'tax_minor' => 1600, 'gross_minor' => 11600], Receipt::query()->firstOrFail()->only(['net_minor', 'tax_minor', 'gross_minor']));
            $line = ReceiptLine::query()->firstOrFail();
            self::assertSame('Item '.$f['variant'], $line->description);
            self::assertSame(11600, $line->gross_minor);
            try {
                $issuer->issue($f['sale'], $f['user'], 'receipt-one', $at->modify('+1 second'));
                self::fail();
            } catch (RuntimeException) {
                self::assertSame(1, Receipt::query()->count());
            }
            $attempt = $this->app->make(ReceiptDeliveryEvidence::class)->record($first->receiptId, 'printer', 'failed', $at, 'PAPER_OUT');
            self::assertSame($attempt, ReceiptDeliveryAttempt::query()->firstOrFail()->id);
            $this->expectException(\Throwable::class);
            $line->update(['gross_minor' => 1]);
        });
    }

    public function test_unpaid_cross_tenant_and_conflicting_replay_are_rejected_generically(): void
    {
        $f = $this->fixture('two');
        $other = Tenant::query()->create(['name' => 'Other', 'jurisdiction_code' => 'KE', 'status' => 'active']);
        $at = new DateTimeImmutable('2026-08-08T16:00:00+03:00');
        $sales = Mockery::mock(ReceiptSaleSnapshots::class);
        $sales->shouldReceive('finalized')->andReturn($this->snapshot($f, $at, 'Tea'));
        $paid = Mockery::mock(ReceiptSettlementStatus::class);
        $paid->shouldReceive('isFullyPaid')->andReturnFalse();
        $this->app->instance(ReceiptSaleSnapshots::class, $sales);
        $this->app->instance(ReceiptSettlementStatus::class, $paid);
        try {
            $this->inTenant($f['tenant'], fn () => $this->app->make(ReceiptIssuer::class)->issue($f['sale'], $f['user'], 'unpaid', $at));
            self::fail();
        } catch (RuntimeException $e) {
            self::assertSame('Receipt cannot be issued for this sale.', $e->getMessage());
        }
        try {
            $this->inTenant((string) $other->id, fn () => $this->app->make(ReceiptIssuer::class)->issue($f['sale'], $f['user'], 'foreign', $at));
            self::fail();
        } catch (RuntimeException $e) {
            self::assertSame('Receipt cannot be issued for this sale.', $e->getMessage());
        }
        self::assertSame(0, Receipt::query()->count());
    }

    private function snapshot(array $f, DateTimeImmutable $at, ?string $description): ReceiptSaleSnapshot
    {
        return new ReceiptSaleSnapshot($f['tenant'], $f['sale'], $f['shift'], $f['register'], $f['user'], 'KES', 10000, 1600, 11600, $at, [new ReceiptSaleLineSnapshot(1, $f['variant'], $description, 2, 5800, 10000, 1600, 11600, 'VAT16', 1600, true)]);
    }

    private function fixture(string $suffix): array
    {
        $tenant = Tenant::query()->create(['name' => "Tenant {$suffix}", 'jurisdiction_code' => 'KE', 'status' => 'active']);
        $company = Company::query()->create(['tenant_id' => $tenant->id, 'name' => "Company {$suffix}", 'status' => 'active']);
        $branch = Branch::query()->create(['tenant_id' => $tenant->id, 'company_id' => $company->id, 'code' => "BR-{$suffix}", 'name' => "Branch {$suffix}", 'status' => 'active']);
        $warehouse = Warehouse::query()->create(['tenant_id' => $tenant->id, 'branch_id' => $branch->id, 'code' => "WH-{$suffix}", 'name' => "Warehouse {$suffix}", 'status' => 'active']);
        $register = Register::query()->create(['tenant_id' => $tenant->id, 'branch_id' => $branch->id, 'code' => "POS-{$suffix}", 'name' => "Register {$suffix}", 'status' => 'active']);
        $user = User::factory()->create();
        $shift = (string) Str::uuid();
        $sale = (string) Str::uuid();
        $variant = (string) Str::uuid();
        DB::table('shifts')->insert(['id' => $shift, 'tenant_id' => $tenant->id, 'register_id' => $register->id, 'opening_user_id' => $user->id, 'status' => 'open', 'currency' => 'KES', 'opening_float_minor' => 0, 'expected_cash_minor' => 0, 'opened_at' => now(), 'idempotency_key' => "shift-{$suffix}", 'created_at' => now(), 'updated_at' => now()]);
        DB::table('sales')->insert(['id' => $sale, 'tenant_id' => $tenant->id, 'shift_id' => $shift, 'register_id' => $register->id, 'warehouse_id' => $warehouse->id, 'actor_user_id' => $user->id, 'status' => 'finalized', 'currency_code' => 'KES', 'net_minor' => 10000, 'tax_minor' => 1600, 'gross_minor' => 11600, 'idempotency_key' => "sale-{$suffix}", 'command_fingerprint' => hash('sha256', $suffix), 'finalized_at' => now(), 'created_at' => now(), 'updated_at' => now()]);

        return ['tenant' => (string) $tenant->id, 'register' => (string) $register->id, 'user' => (string) $user->id, 'shift' => $shift, 'sale' => $sale, 'variant' => $variant];
    }

    private function inTenant(string $tenantId, callable $callback): mixed
    {
        return $this->app->make(TenantScope::class)->run(TenantId::fromString($tenantId), $callback);
    }
}
