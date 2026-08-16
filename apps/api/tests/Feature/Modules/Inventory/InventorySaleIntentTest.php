<?php

declare(strict_types=1);

namespace Tests\Feature\Modules\Inventory;

use App\Modules\Catalogue\Application\Contracts\CatalogueManager;
use App\Modules\Catalogue\Domain\ProductVariant;
use App\Modules\Catalogue\Domain\UnitOfMeasure;
use App\Modules\Inventory\Application\Contracts\InventoryLedger;
use App\Modules\Inventory\Application\Contracts\InventorySaleIntent;
use App\Modules\Inventory\Application\DatabaseInventorySaleIntent;
use App\Modules\Inventory\Domain\StockMovement;
use App\Modules\Inventory\Domain\StockReservation;
use App\Modules\Tenancy\Application\Contracts\OrganizationLocations;
use App\Modules\Tenancy\Application\TenantScope;
use App\Modules\Tenancy\Domain\Tenant;
use App\Modules\Tenancy\Domain\TenantId;
use App\Modules\Tenancy\Domain\Warehouse;
use DomainException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use LogicException;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

final class InventorySaleIntentTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function reservations_reduce_availability_and_prevent_overselling(): void
    {
        [$tenantId, $warehouseId, $variantId] = $this->fixture('Availability', 10);

        $this->inTenant($tenantId, function (InventorySaleIntent $intents) use ($warehouseId, $variantId): void {
            $intents->reserve((string) Str::uuid(), $warehouseId, $variantId, 7, 'reserve-7');
            self::assertSame(3, $intents->available($warehouseId, $variantId));
            $this->expectException(DomainException::class);
            $intents->reserve((string) Str::uuid(), $warehouseId, $variantId, 4, 'reserve-too-many');
        });
    }

    #[Test]
    public function reserve_replay_is_exact_and_conflicting_reuse_fails(): void
    {
        [$tenantId, $warehouseId, $variantId] = $this->fixture('Replay', 10);
        $id = (string) Str::uuid();
        $this->inTenant($tenantId, function (InventorySaleIntent $intents) use ($id, $warehouseId, $variantId): void {
            $first = $intents->reserve($id, $warehouseId, $variantId, 3, 'reserve-replay');
            self::assertEquals($first, $intents->reserve($id, $warehouseId, $variantId, 3, 'reserve-replay'));
            self::assertSame(1, StockReservation::query()->count());
            try {
                $intents->reserve($id, $warehouseId, $variantId, 4, 'reserve-replay');
                self::fail('Conflicting reservation replay succeeded.');
            } catch (DomainException) {
                self::assertTrue(true);
            }
        });
    }

    #[Test]
    public function finalize_decrements_stock_exactly_once_and_is_terminal(): void
    {
        [$tenantId, $warehouseId, $variantId] = $this->fixture('Finalize', 10);
        $id = (string) Str::uuid();
        $this->inTenant($tenantId, function (InventorySaleIntent $intents) use ($id, $warehouseId, $variantId): void {
            $intents->reserve($id, $warehouseId, $variantId, 4, 'reserve-finalize');
            $first = $intents->finalize($id, 'finalize');
            $replay = $intents->finalize($id, 'finalize');
            self::assertEquals($first, $replay);
            self::assertSame('finalized', $first->status);
            self::assertNotNull($first->movementId);
            self::assertSame(6, $intents->available($warehouseId, $variantId));
            self::assertSame(2, StockMovement::query()->count());
            $this->expectException(DomainException::class);
            $intents->release($id, 'release-after-finalize');
        });
    }

    #[Test]
    public function release_restores_availability_without_a_stock_movement(): void
    {
        [$tenantId, $warehouseId, $variantId] = $this->fixture('Release', 8);
        $id = (string) Str::uuid();
        $this->inTenant($tenantId, function (InventorySaleIntent $intents) use ($id, $warehouseId, $variantId): void {
            $intents->reserve($id, $warehouseId, $variantId, 5, 'reserve-release');
            $first = $intents->release($id, 'release');
            self::assertEquals($first, $intents->release($id, 'release'));
            self::assertSame('released', $first->status);
            self::assertSame(8, $intents->available($warehouseId, $variantId));
            self::assertSame(1, StockMovement::query()->count());
            $this->expectException(DomainException::class);
            $intents->finalize($id, 'finalize-after-release');
        });
    }

    #[Test]
    public function cross_tenant_inactive_and_unknown_references_are_rejected(): void
    {
        [$firstTenant, $warehouseId, $variantId] = $this->fixture('First', 2);
        [$secondTenant] = $this->fixture('Second', 2);
        $reservationId = (string) Str::uuid();
        $this->inTenant($firstTenant, fn (InventorySaleIntent $intents) => $intents->reserve($reservationId, $warehouseId, $variantId, 1, 'first'));

        try {
            $this->inTenant($secondTenant, fn (InventorySaleIntent $intents) => $intents->finalize($reservationId, 'cross'));
            self::fail('Cross-tenant reservation leaked.');
        } catch (DomainException) {
            self::assertTrue(true);
        }

        Warehouse::query()->whereKey($warehouseId)->update(['status' => 'inactive']);
        try {
            $this->inTenant($firstTenant, fn (InventorySaleIntent $intents) => $intents->reserve((string) Str::uuid(), $warehouseId, $variantId, 1, 'inactive-warehouse'));
            self::fail('Inactive warehouse was accepted.');
        } catch (DomainException) {
            self::assertTrue(true);
        }
        Warehouse::query()->whereKey($warehouseId)->update(['status' => 'active']);
        ProductVariant::query()->whereKey($variantId)->update(['status' => 'inactive']);
        $this->expectException(DomainException::class);
        $this->inTenant($firstTenant, fn (InventorySaleIntent $intents) => $intents->reserve((string) Str::uuid(), $warehouseId, $variantId, 1, 'inactive-variant'));
    }

    #[Test]
    public function reservation_model_schema_and_service_enforce_immutability_rls_and_locking(): void
    {
        [$tenantId, $warehouseId, $variantId] = $this->fixture('Guards', 2);
        $id = (string) Str::uuid();
        $this->inTenant($tenantId, function (InventorySaleIntent $intents) use ($id, $warehouseId, $variantId): void {
            $intents->reserve($id, $warehouseId, $variantId, 1, 'guard');
            try {
                StockReservation::query()->findOrFail($id)->update(['quantity' => 2]);
                self::fail('Reservation fact was mutable.');
            } catch (LogicException) {
                self::assertTrue(true);
            }
        });

        $service = file_get_contents((new \ReflectionClass(DatabaseInventorySaleIntent::class))->getFileName());
        $migrationPath = glob(dirname(__DIR__, 4).'/app/Modules/Inventory/Database/Migrations/*create_inventory_stock_reservations_table.php')[0] ?? null;
        self::assertNotNull($migrationPath);
        $migration = file_get_contents($migrationPath);
        self::assertStringContainsString('lockForUpdate()', $service);
        self::assertStringContainsString('pg_advisory_xact_lock', $service);
        self::assertStringContainsString('FORCE ROW LEVEL SECURITY', $migration);
        self::assertStringContainsString('inventory_stock_reservations_transition_guard', $migration);
        self::assertStringContainsString("foreign(['tenant_id', 'stock_movement_id'])", $migration);
    }

    /** @return array{string, string, string} */
    private function fixture(string $name, int $stock): array
    {
        $tenant = Tenant::query()->create(['name' => $name, 'jurisdiction_code' => 'KE', 'status' => 'active']);
        $tenantId = (string) $tenant->getKey();
        $unit = UnitOfMeasure::query()->create(['tenant_id' => $tenantId, 'code' => 'EA', 'name' => 'Each', 'status' => 'active']);

        return $this->inTenant($tenantId, function (InventorySaleIntent $intents) use ($name, $tenantId, $unit, $stock): array {
            $locations = $this->app->make(OrganizationLocations::class);
            $company = $locations->createCompany($name);
            $branch = $locations->createBranch((string) $company->getKey(), 'MAIN', 'Main');
            $warehouse = $locations->createWarehouse((string) $branch->getKey(), 'WH', 'Warehouse');
            $variant = $this->app->make(CatalogueManager::class)->createProductWithDefaultVariant('Item', 'SKU-'.bin2hex(random_bytes(3)), (string) $unit->getKey())->defaultVariant;
            $this->app->make(InventoryLedger::class)->postReceipt((string) $warehouse->getKey(), (string) $variant->getKey(), $stock, 'opening-stock');

            return [$tenantId, (string) $warehouse->getKey(), (string) $variant->getKey()];
        });
    }

    private function inTenant(string $tenantId, callable $callback): mixed
    {
        return $this->app->make(TenantScope::class)->run(TenantId::fromString($tenantId), fn () => $callback($this->app->make(InventorySaleIntent::class)));
    }
}
