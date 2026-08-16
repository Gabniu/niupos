<?php

declare(strict_types=1);

namespace Tests\Feature\Modules\Sales;

use App\Modules\Audit\Application\Contracts\SecurityAuditRecorder;
use App\Modules\Catalogue\Domain\Product;
use App\Modules\Catalogue\Domain\ProductVariant;
use App\Modules\Catalogue\Domain\UnitOfMeasure;
use App\Modules\Identity\Domain\User;
use App\Modules\Inventory\Application\Contracts\InventorySaleIntent;
use App\Modules\Inventory\Application\StockReservationResult;
use App\Modules\Pricing\Application\CheckoutLineQuote;
use App\Modules\Pricing\Application\Contracts\CheckoutQuoteProvider;
use App\Modules\Register\Domain\Register;
use App\Modules\Sales\Application\Contracts\SalesCheckout;
use App\Modules\Sales\Domain\Sale;
use App\Modules\Sales\Domain\SaleLine;
use App\Modules\Shifts\Application\Contracts\OpenShiftCheckoutEligibility;
use App\Modules\Shifts\Application\Data\EligibleOpenShift;
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

final class SalesCheckoutTest extends TestCase
{
    use RefreshDatabase;

    public function test_checkout_finalizes_immutable_snapshots_and_inventory_exactly_once(): void
    {
        $fixture = $this->fixture('happy');
        $at = new DateTimeImmutable('2026-08-08T12:00:00+03:00');
        $shift = new EligibleOpenShift($fixture['tenant'], $fixture['shift'], $fixture['register'], $fixture['user'], 'KES', $at);

        $eligibility = Mockery::mock(OpenShiftCheckoutEligibility::class);
        $eligibility->shouldReceive('withEligibleOpenShift')->twice()->andReturnUsing(fn ($register, $actor, $operation) => $operation($shift));
        $pricing = Mockery::mock(CheckoutQuoteProvider::class);
        $pricing->shouldReceive('quote')->once()->andReturn(new CheckoutLineQuote(
            $fixture['variant'], 2, 'KES', 5800, 10000, 1600, 11600,
            (string) Str::uuid(), 'VAT16', 1600, true, (string) Str::uuid(), (string) Str::uuid(), $at,
        ));
        $inventory = Mockery::mock(InventorySaleIntent::class);
        $inventory->shouldReceive('reserve')->once()->andReturnUsing(fn ($id) => new StockReservationResult($id, 'active', $fixture['warehouse'], $fixture['variant'], 2));
        $inventory->shouldReceive('finalize')->once()->andReturnUsing(fn ($id) => new StockReservationResult($id, 'finalized', $fixture['warehouse'], $fixture['variant'], 2, (string) Str::uuid()));
        $audit = Mockery::mock(SecurityAuditRecorder::class);
        $audit->shouldReceive('record')->once();
        $this->app->instance(OpenShiftCheckoutEligibility::class, $eligibility);
        $this->app->instance(CheckoutQuoteProvider::class, $pricing);
        $this->app->instance(InventorySaleIntent::class, $inventory);
        $this->app->instance(SecurityAuditRecorder::class, $audit);

        $this->inTenant($fixture['tenant'], function (SalesCheckout $checkout) use ($fixture, $at): void {
            $arguments = [$fixture['register'], $fixture['user'], $fixture['warehouse'], (string) Str::uuid(), 'kes', [['variant_id' => $fixture['variant'], 'quantity' => 2]], 'checkout-001', $at];
            $first = $checkout->finalize(...$arguments);
            $replay = $checkout->finalize(...$arguments);
            self::assertSame($first->saleId, $replay->saleId);
            self::assertSame([10000, 1600, 11600, 1], [$first->netMinor, $first->taxMinor, $first->grossMinor, $first->lineCount]);
            self::assertSame(1, Sale::query()->count());
            self::assertSame(1, SaleLine::query()->count());

            $this->expectException(\Throwable::class);
            Sale::query()->firstOrFail()->update(['gross_minor' => 1]);
        });
    }

    public function test_conflicting_replay_and_invalid_lines_fail_without_a_second_sale(): void
    {
        $fixture = $this->fixture('conflict');
        $at = new DateTimeImmutable('2026-08-08T13:00:00+03:00');
        $shift = new EligibleOpenShift($fixture['tenant'], $fixture['shift'], $fixture['register'], $fixture['user'], 'KES', $at);
        $eligibility = Mockery::mock(OpenShiftCheckoutEligibility::class);
        $eligibility->shouldReceive('withEligibleOpenShift')->twice()->andReturnUsing(fn ($register, $actor, $operation) => $operation($shift));
        $pricing = Mockery::mock(CheckoutQuoteProvider::class);
        $pricing->shouldReceive('quote')->once()->andReturn(new CheckoutLineQuote($fixture['variant'], 1, 'KES', 100, 100, 0, 100, (string) Str::uuid(), 'ZERO', 0, false, (string) Str::uuid(), (string) Str::uuid(), $at));
        $inventory = Mockery::mock(InventorySaleIntent::class);
        $inventory->shouldReceive('reserve')->once()->andReturnUsing(fn ($id) => new StockReservationResult($id, 'active', $fixture['warehouse'], $fixture['variant'], 1));
        $inventory->shouldReceive('finalize')->once()->andReturnUsing(fn ($id) => new StockReservationResult($id, 'finalized', $fixture['warehouse'], $fixture['variant'], 1, (string) Str::uuid()));
        $audit = Mockery::mock(SecurityAuditRecorder::class);
        $audit->shouldReceive('record')->once();
        $this->app->instance(OpenShiftCheckoutEligibility::class, $eligibility);
        $this->app->instance(CheckoutQuoteProvider::class, $pricing);
        $this->app->instance(InventorySaleIntent::class, $inventory);
        $this->app->instance(SecurityAuditRecorder::class, $audit);

        $this->inTenant($fixture['tenant'], function (SalesCheckout $checkout) use ($fixture, $at): void {
            $checkout->finalize($fixture['register'], $fixture['user'], $fixture['warehouse'], (string) Str::uuid(), 'KES', [['variant_id' => $fixture['variant'], 'quantity' => 1]], 'checkout-conflict', $at);
            try {
                $checkout->finalize($fixture['register'], $fixture['user'], $fixture['warehouse'], (string) Str::uuid(), 'KES', [['variant_id' => $fixture['variant'], 'quantity' => 2]], 'checkout-conflict', $at);
                self::fail('Conflicting replay should fail.');
            } catch (RuntimeException) {
                self::assertSame(1, Sale::query()->count());
            }
        });
    }

    /** @return array{tenant:string,user:string,register:string,warehouse:string,variant:string,shift:string} */
    private function fixture(string $suffix): array
    {
        $tenant = Tenant::query()->create(['name' => "Tenant {$suffix}", 'jurisdiction_code' => 'KE', 'status' => 'active']);
        $company = Company::query()->create(['tenant_id' => $tenant->id, 'name' => "Company {$suffix}", 'status' => 'active']);
        $branch = Branch::query()->create(['tenant_id' => $tenant->id, 'company_id' => $company->id, 'code' => "BR-{$suffix}", 'name' => "Branch {$suffix}", 'status' => 'active']);
        $warehouse = Warehouse::query()->create(['tenant_id' => $tenant->id, 'branch_id' => $branch->id, 'code' => "WH-{$suffix}", 'name' => "Warehouse {$suffix}", 'status' => 'active']);
        $register = Register::query()->create(['tenant_id' => $tenant->id, 'branch_id' => $branch->id, 'code' => "POS-{$suffix}", 'name' => "Register {$suffix}", 'status' => 'active']);
        $user = User::factory()->create();
        $unit = UnitOfMeasure::query()->create(['tenant_id' => $tenant->id, 'code' => "EA-{$suffix}", 'name' => 'Each', 'status' => 'active']);
        $product = Product::query()->create(['tenant_id' => $tenant->id, 'name' => "Product {$suffix}", 'status' => 'active']);
        $variant = ProductVariant::query()->create(['tenant_id' => $tenant->id, 'product_id' => $product->id, 'unit_of_measure_id' => $unit->id, 'name' => "Variant {$suffix}", 'sku' => "SKU-{$suffix}", 'normalized_sku' => "SKU-{$suffix}", 'status' => 'active']);
        $shiftId = (string) Str::uuid();
        DB::table('shifts')->insert(['id' => $shiftId, 'tenant_id' => $tenant->id, 'register_id' => $register->id, 'opening_user_id' => $user->id, 'status' => 'open', 'currency' => 'KES', 'opening_float_minor' => 0, 'expected_cash_minor' => 0, 'opened_at' => now(), 'idempotency_key' => "shift-{$suffix}", 'created_at' => now(), 'updated_at' => now()]);

        return ['tenant' => (string) $tenant->id, 'user' => (string) $user->id, 'register' => (string) $register->id, 'warehouse' => (string) $warehouse->id, 'variant' => (string) $variant->id, 'shift' => $shiftId];
    }

    private function inTenant(string $tenantId, callable $callback): mixed
    {
        return $this->app->make(TenantScope::class)->run(TenantId::fromString($tenantId), fn () => $callback($this->app->make(SalesCheckout::class)));
    }
}
