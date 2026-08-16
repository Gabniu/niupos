<?php

declare(strict_types=1);

namespace App\Modules\Inventory\Application;

use App\Modules\Catalogue\Application\Contracts\ActiveVariantLookup;
use App\Modules\Inventory\Application\Contracts\InventoryLedger;
use App\Modules\Inventory\Domain\StockBalance;
use App\Modules\Inventory\Domain\StockMovement;
use App\Modules\Inventory\Domain\StockMovementType;
use App\Modules\Tenancy\Application\TenantContext;
use DomainException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use InvalidArgumentException;

final readonly class DatabaseInventoryLedger implements InventoryLedger
{
    public function __construct(
        private TenantContext $tenantContext,
        private ActiveVariantLookup $variants,
    ) {}

    public function postReceipt(string $warehouseId, string $variantId, int $quantity, string $idempotencyKey): PostedStockMovement
    {
        if ($quantity <= 0) {
            throw new InvalidArgumentException('Receipt quantity must be positive.');
        }

        return $this->post(StockMovementType::Receipt, $warehouseId, $variantId, $quantity, $idempotencyKey);
    }

    public function postAdjustment(string $warehouseId, string $variantId, int $quantityDelta, string $idempotencyKey): PostedStockMovement
    {
        if ($quantityDelta === 0) {
            throw new InvalidArgumentException('Adjustment quantity must be non-zero.');
        }

        return $this->post(StockMovementType::Adjustment, $warehouseId, $variantId, $quantityDelta, $idempotencyKey);
    }

    public function postSale(string $warehouseId, string $variantId, int $quantity, string $idempotencyKey): PostedStockMovement
    {
        if ($quantity <= 0) {
            throw new InvalidArgumentException('Sale quantity must be positive.');
        }

        return $this->post(StockMovementType::Sale, $warehouseId, $variantId, -$quantity, $idempotencyKey);
    }

    public function balance(string $warehouseId, string $variantId): int
    {
        $tenantId = (string) $this->tenantContext->id();

        return (int) (StockBalance::query()
            ->where('tenant_id', $tenantId)
            ->where('warehouse_id', $warehouseId)
            ->where('product_variant_id', $variantId)
            ->value('quantity') ?? 0);
    }

    private function post(StockMovementType $type, string $warehouseId, string $variantId, int $delta, string $idempotencyKey): PostedStockMovement
    {
        $tenantId = (string) $this->tenantContext->id();
        $key = trim($idempotencyKey);
        if ($key === '' || strlen($key) > 128) {
            throw new InvalidArgumentException('An idempotency key of at most 128 characters is required.');
        }
        if (! $this->variants->existsForCurrentTenant($variantId) || ! $this->activeWarehouseExists($tenantId, $warehouseId)) {
            throw new DomainException('An active tenant warehouse and catalogue variant are required.');
        }

        $hash = hash('sha256', implode('|', [$type->value, $warehouseId, $variantId, (string) $delta]));

        return DB::transaction(function () use ($tenantId, $type, $warehouseId, $variantId, $delta, $key, $hash): PostedStockMovement {
            if (DB::getDriverName() === 'pgsql') {
                DB::select('select pg_advisory_xact_lock(hashtextextended(?, 0))', [$tenantId.'|'.$key]);
            }

            $existing = StockMovement::query()->where('tenant_id', $tenantId)->where('idempotency_key', $key)->first();
            if ($existing !== null) {
                if (! hash_equals($existing->command_hash, $hash)) {
                    throw new DomainException('The idempotency key was already used for a different inventory command.');
                }

                return $this->result($existing);
            }

            DB::table('inventory_stock_balances')->insertOrIgnore([
                'id' => (string) Str::uuid(), 'tenant_id' => $tenantId,
                'warehouse_id' => $warehouseId, 'product_variant_id' => $variantId, 'quantity' => 0,
                'created_at' => now(), 'updated_at' => now(),
            ]);
            /** @var StockBalance $balance */
            $balance = StockBalance::query()
                ->where('tenant_id', $tenantId)->where('warehouse_id', $warehouseId)
                ->where('product_variant_id', $variantId)->lockForUpdate()->firstOrFail();
            $next = $balance->quantity + $delta;
            if ($next < 0 && ! (bool) config('inventory.allow_negative_stock', false)) {
                throw new DomainException('Negative stock is not allowed.');
            }
            $balance->quantity = $next;
            $balance->save();

            $movement = StockMovement::query()->create([
                'tenant_id' => $tenantId, 'warehouse_id' => $warehouseId, 'product_variant_id' => $variantId,
                'movement_type' => $type, 'quantity_delta' => $delta, 'balance_after' => $next,
                'idempotency_key' => $key, 'command_hash' => $hash,
            ]);

            return $this->result($movement);
        }, 3);
    }

    private function activeWarehouseExists(string $tenantId, string $warehouseId): bool
    {
        return DB::table('warehouses')->where('tenant_id', $tenantId)->where('id', $warehouseId)->where('status', 'active')->exists();
    }

    private function result(StockMovement $movement): PostedStockMovement
    {
        return new PostedStockMovement((string) $movement->getKey(), $movement->movement_type->value, $movement->quantity_delta, $movement->balance_after);
    }
}
