<?php

declare(strict_types=1);

namespace Tests\Feature\Modules\Inventory;

use App\Modules\Catalogue\Application\Contracts\CatalogueManager;
use App\Modules\Catalogue\Domain\ProductVariant;
use App\Modules\Catalogue\Domain\UnitOfMeasure;
use App\Modules\Inventory\Application\Contracts\InventoryLedger;
use App\Modules\Inventory\Application\DatabaseInventoryLedger;
use App\Modules\Inventory\Domain\StockMovement;
use App\Modules\Tenancy\Application\Contracts\OrganizationLocations;
use App\Modules\Tenancy\Application\TenantScope;
use App\Modules\Tenancy\Domain\Tenant;
use App\Modules\Tenancy\Domain\TenantId;
use App\Modules\Tenancy\Domain\Warehouse;
use DomainException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use LogicException;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

final class InventoryLedgerTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function tenant_context_is_required(): void
    {
        $this->expectException(LogicException::class);
        $this->app->make(InventoryLedger::class)->balance('warehouse', 'variant');
    }

    #[Test]
    public function receipt_and_adjustment_append_movements_and_update_the_balance(): void
    {
        [$tenantId, $warehouseId, $variantId] = $this->fixture('Ledger');

        $this->inTenant($tenantId, function (InventoryLedger $ledger) use ($warehouseId, $variantId): void {
            $receipt = $ledger->postReceipt($warehouseId, $variantId, 1500, 'receipt-1');
            $adjustment = $ledger->postAdjustment($warehouseId, $variantId, -250, 'adjustment-1');

            self::assertSame([1500, 1250], [$receipt->balanceAfter, $adjustment->balanceAfter]);
            self::assertSame(1250, $ledger->balance($warehouseId, $variantId));
            self::assertSame(2, StockMovement::query()->count());
        });
    }

    #[Test]
    public function identical_idempotent_replay_returns_the_original_result_without_double_counting(): void
    {
        [$tenantId, $warehouseId, $variantId] = $this->fixture('Replay');
        $this->inTenant($tenantId, function (InventoryLedger $ledger) use ($warehouseId, $variantId): void {
            $first = $ledger->postReceipt($warehouseId, $variantId, 10, 'same-command');
            $replay = $ledger->postReceipt($warehouseId, $variantId, 10, 'same-command');
            self::assertEquals($first, $replay);
            self::assertSame(10, $ledger->balance($warehouseId, $variantId));
            self::assertSame(1, StockMovement::query()->count());
        });
    }

    #[Test]
    public function conflicting_idempotency_key_reuse_is_rejected(): void
    {
        [$tenantId, $warehouseId, $variantId] = $this->fixture('Conflict');
        $this->expectException(DomainException::class);
        $this->inTenant($tenantId, function (InventoryLedger $ledger) use ($warehouseId, $variantId): void {
            $ledger->postReceipt($warehouseId, $variantId, 10, 'same-key');
            $ledger->postReceipt($warehouseId, $variantId, 11, 'same-key');
        });
    }

    #[Test]
    public function cross_tenant_and_inactive_references_are_rejected(): void
    {
        [$firstTenant, $warehouseId, $variantId] = $this->fixture('First');
        [$secondTenant] = $this->fixture('Second');

        try {
            $this->inTenant($secondTenant, fn (InventoryLedger $ledger) => $ledger->postReceipt($warehouseId, $variantId, 1, 'cross'));
            self::fail('Cross-tenant references were accepted.');
        } catch (DomainException) {
            self::assertTrue(true);
        }

        Warehouse::query()->whereKey($warehouseId)->update(['status' => 'inactive']);
        $this->expectException(DomainException::class);
        $this->inTenant($firstTenant, fn (InventoryLedger $ledger) => $ledger->postReceipt($warehouseId, $variantId, 1, 'inactive'));
    }

    #[Test]
    public function inactive_variant_is_rejected(): void
    {
        [$tenantId, $warehouseId, $variantId] = $this->fixture('Inactive variant');
        ProductVariant::query()->whereKey($variantId)->update(['status' => 'inactive']);

        $this->expectException(DomainException::class);
        $this->inTenant($tenantId, fn (InventoryLedger $ledger) => $ledger->postReceipt($warehouseId, $variantId, 1, 'inactive-variant'));
    }

    #[Test]
    public function negative_stock_is_denied_by_default(): void
    {
        [$tenantId, $warehouseId, $variantId] = $this->fixture('Negative');
        $this->expectException(DomainException::class);
        $this->inTenant($tenantId, fn (InventoryLedger $ledger) => $ledger->postAdjustment($warehouseId, $variantId, -1, 'negative'));
    }

    #[Test]
    public function movement_models_and_postgresql_are_append_only_and_balance_updates_lock_rows(): void
    {
        [$tenantId, $warehouseId, $variantId] = $this->fixture('Immutable');
        $movementId = $this->inTenant($tenantId, fn (InventoryLedger $ledger) => $ledger->postReceipt($warehouseId, $variantId, 1, 'immutable')->movementId);

        try {
            $this->inTenant($tenantId, fn () => StockMovement::query()->findOrFail($movementId)->update(['quantity_delta' => 2]));
            self::fail('A movement was mutated.');
        } catch (LogicException) {
            self::assertTrue(true);
        }

        $service = file_get_contents((new \ReflectionClass(DatabaseInventoryLedger::class))->getFileName());
        $migrationPath = glob(dirname(__DIR__, 4).'/app/Modules/Inventory/Database/Migrations/*create_inventory_ledger_tables.php')[0] ?? null;
        self::assertNotNull($migrationPath);
        $migration = file_get_contents($migrationPath);
        self::assertStringContainsString('lockForUpdate()', $service);
        self::assertStringContainsString('pg_advisory_xact_lock', $service);
        self::assertStringContainsString('inventory_stock_movements_no_update', $migration);
        self::assertStringContainsString('inventory_stock_movements_no_delete', $migration);
        self::assertStringContainsString('FORCE ROW LEVEL SECURITY', $migration);
    }

    /** @return array{string, string, string} */
    private function fixture(string $name): array
    {
        $tenant = Tenant::query()->create(['name' => $name, 'jurisdiction_code' => 'KE', 'status' => 'active']);
        $tenantId = (string) $tenant->getKey();
        $unit = UnitOfMeasure::query()->create(['tenant_id' => $tenantId, 'code' => 'EA', 'name' => 'Each', 'status' => 'active']);

        return $this->inTenant($tenantId, function () use ($name, $tenantId, $unit): array {
            $locations = $this->app->make(OrganizationLocations::class);
            $company = $locations->createCompany($name);
            $branch = $locations->createBranch((string) $company->getKey(), 'MAIN', 'Main');
            $warehouse = $locations->createWarehouse((string) $branch->getKey(), 'WH', 'Warehouse');
            $variant = $this->app->make(CatalogueManager::class)->createProductWithDefaultVariant('Item', 'SKU-'.bin2hex(random_bytes(3)), (string) $unit->getKey())->defaultVariant;

            return [$tenantId, (string) $warehouse->getKey(), (string) $variant->getKey()];
        });
    }

    private function inTenant(string $tenantId, callable $callback): mixed
    {
        return $this->app->make(TenantScope::class)->run(TenantId::fromString($tenantId), fn () => $callback($this->app->make(InventoryLedger::class)));
    }
}
