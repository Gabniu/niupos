<?php

declare(strict_types=1);

namespace Tests\Feature\Modules\Sales;

use App\Modules\Catalogue\Domain\Product;
use App\Modules\Catalogue\Domain\ProductVariant;
use App\Modules\Catalogue\Domain\UnitOfMeasure;
use App\Modules\Identity\Domain\User;
use App\Modules\Register\Domain\Register;
use App\Modules\Sales\Application\Contracts\FinalizedSaleSnapshotReader;
use App\Modules\Tenancy\Application\TenantScope;
use App\Modules\Tenancy\Domain\Branch;
use App\Modules\Tenancy\Domain\Company;
use App\Modules\Tenancy\Domain\Tenant;
use App\Modules\Tenancy\Domain\TenantId;
use App\Modules\Tenancy\Domain\Warehouse;
use DateTimeImmutable;
use Error;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use RuntimeException;
use Tests\TestCase;

final class FinalizedSaleSnapshotReaderTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_returns_a_complete_immutable_snapshot_with_ordered_lines(): void
    {
        $fixture = $this->fixture('complete', 'finalized');
        $this->insertLine($fixture, 2, 3, 200, 500, 100, 600, 'VAT20', 2000, false);
        $this->insertLine($fixture, 1, 2, 250, 500, 0, 500, 'ZERO', 0, true);

        $snapshot = $this->inTenant($fixture['tenant'], fn (FinalizedSaleSnapshotReader $reader) => $reader->resolve($fixture['sale']));

        self::assertSame([
            $fixture['sale'], $fixture['tenant'], $fixture['shift'], $fixture['register'], $fixture['warehouse'],
            $fixture['user'], 'KES', 1000, 100, 1100,
        ], [
            $snapshot->saleId, $snapshot->tenantId, $snapshot->shiftId, $snapshot->registerId, $snapshot->warehouseId,
            $snapshot->actorUserId, $snapshot->currencyCode, $snapshot->netMinor, $snapshot->taxMinor, $snapshot->grossMinor,
        ]);
        self::assertEquals(new DateTimeImmutable('2026-08-08T12:00:00+03:00'), $snapshot->finalizedAt);
        self::assertSame([1, 2], array_map(static fn ($line) => $line->lineNumber, $snapshot->lines));
        self::assertSame(['inclusive', 'exclusive'], array_map(static fn ($line) => $line->taxMode, $snapshot->lines));
        self::assertSame([$fixture['variant'], 2, 250, 500, 0, 500, 'ZERO', 0], [
            $snapshot->lines[0]->variantId, $snapshot->lines[0]->quantity, $snapshot->lines[0]->unitPriceMinor,
            $snapshot->lines[0]->netMinor, $snapshot->lines[0]->taxMinor, $snapshot->lines[0]->grossMinor,
            $snapshot->lines[0]->taxCode, $snapshot->lines[0]->taxRateBasisPoints,
        ]);

        $this->expectException(Error::class);
        $snapshot->grossMinor = 1;
    }

    public function test_cross_tenant_and_missing_sales_share_the_generic_rejection(): void
    {
        $first = $this->fixture('first', 'finalized');
        $second = $this->fixture('second', 'finalized');

        foreach ([$first['sale'], (string) Str::uuid()] as $saleId) {
            try {
                $this->inTenant($second['tenant'], fn (FinalizedSaleSnapshotReader $reader) => $reader->resolve($saleId));
                self::fail('The snapshot must not cross the tenant boundary.');
            } catch (RuntimeException $exception) {
                self::assertSame('Finalized sale was not found.', $exception->getMessage());
            }
        }
    }

    public function test_non_finalized_sale_uses_the_same_generic_rejection(): void
    {
        $fixture = $this->fixture('pending', 'pending');

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Finalized sale was not found.');
        $this->inTenant($fixture['tenant'], fn (FinalizedSaleSnapshotReader $reader) => $reader->resolve($fixture['sale']));
    }

    /** @return array{tenant:string,user:string,register:string,warehouse:string,variant:string,shift:string,sale:string} */
    private function fixture(string $suffix, string $status): array
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
        $shift = (string) Str::uuid();
        $sale = (string) Str::uuid();
        DB::table('shifts')->insert(['id' => $shift, 'tenant_id' => $tenant->id, 'register_id' => $register->id, 'opening_user_id' => $user->id, 'status' => 'open', 'currency' => 'KES', 'opening_float_minor' => 0, 'expected_cash_minor' => 0, 'opened_at' => now(), 'idempotency_key' => "shift-{$suffix}", 'created_at' => now(), 'updated_at' => now()]);
        DB::table('sales')->insert(['id' => $sale, 'tenant_id' => $tenant->id, 'shift_id' => $shift, 'register_id' => $register->id, 'warehouse_id' => $warehouse->id, 'actor_user_id' => $user->id, 'status' => $status, 'currency_code' => 'KES', 'net_minor' => 1000, 'tax_minor' => 100, 'gross_minor' => 1100, 'idempotency_key' => "sale-{$suffix}", 'command_fingerprint' => str_repeat('a', 64), 'finalized_at' => '2026-08-08T12:00:00+03:00', 'created_at' => now(), 'updated_at' => now()]);

        return ['tenant' => (string) $tenant->id, 'user' => (string) $user->id, 'register' => (string) $register->id, 'warehouse' => (string) $warehouse->id, 'variant' => (string) $variant->id, 'shift' => $shift, 'sale' => $sale];
    }

    /** @param array{tenant:string,variant:string,sale:string} $fixture */
    private function insertLine(array $fixture, int $number, int $quantity, int $unit, int $net, int $tax, int $gross, string $taxCode, int $rate, bool $inclusive): void
    {
        DB::table('sale_lines')->insert(['id' => (string) Str::uuid(), 'tenant_id' => $fixture['tenant'], 'sale_id' => $fixture['sale'], 'variant_id' => $fixture['variant'], 'reservation_id' => (string) Str::uuid(), 'line_number' => $number, 'quantity' => $quantity, 'currency_code' => 'KES', 'unit_price_minor' => $unit, 'net_minor' => $net, 'tax_minor' => $tax, 'gross_minor' => $gross, 'tax_category_id' => (string) Str::uuid(), 'tax_code' => $taxCode, 'tax_rate_basis_points' => $rate, 'tax_inclusive' => $inclusive, 'price_book_id' => (string) Str::uuid(), 'price_id' => (string) Str::uuid(), 'quoted_at' => now()]);
    }

    private function inTenant(string $tenantId, callable $callback): mixed
    {
        return $this->app->make(TenantScope::class)->run(TenantId::fromString($tenantId), fn () => $callback($this->app->make(FinalizedSaleSnapshotReader::class)));
    }
}
