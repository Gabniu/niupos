<?php

declare(strict_types=1);

namespace App\Modules\Inventory\Application;

use App\Modules\Catalogue\Application\Contracts\ActiveVariantLookup;
use App\Modules\Inventory\Application\Contracts\InventoryLedger;
use App\Modules\Inventory\Application\Contracts\InventorySaleIntent;
use App\Modules\Inventory\Domain\StockBalance;
use App\Modules\Inventory\Domain\StockReservation;
use App\Modules\Inventory\Domain\StockReservationStatus;
use App\Modules\Tenancy\Application\TenantContext;
use DomainException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use InvalidArgumentException;

final readonly class DatabaseInventorySaleIntent implements InventorySaleIntent
{
    public function __construct(
        private TenantContext $tenantContext,
        private ActiveVariantLookup $variants,
        private InventoryLedger $ledger,
    ) {}

    public function available(string $warehouseId, string $variantId): int
    {
        $tenantId = (string) $this->tenantContext->id();
        $onHand = (int) (StockBalance::query()->where('tenant_id', $tenantId)->where('warehouse_id', $warehouseId)->where('product_variant_id', $variantId)->value('quantity') ?? 0);
        $reserved = (int) StockReservation::query()->where('tenant_id', $tenantId)->where('warehouse_id', $warehouseId)->where('product_variant_id', $variantId)->where('status', StockReservationStatus::Active)->sum('quantity');

        return $onHand - $reserved;
    }

    public function reserve(string $reservationId, string $warehouseId, string $variantId, int $quantity, string $idempotencyKey): StockReservationResult
    {
        $tenantId = (string) $this->tenantContext->id();
        $this->assertUuid($reservationId);
        $key = $this->key($idempotencyKey);
        if ($quantity <= 0) {
            throw new InvalidArgumentException('Reservation quantity must be positive.');
        }
        if (! $this->variants->existsForCurrentTenant($variantId) || ! $this->activeWarehouseExists($tenantId, $warehouseId)) {
            throw new DomainException('An active tenant warehouse and catalogue variant are required.');
        }
        $hash = hash('sha256', implode('|', ['reserve', $reservationId, $warehouseId, $variantId, (string) $quantity]));

        return DB::transaction(function () use ($tenantId, $reservationId, $warehouseId, $variantId, $quantity, $key, $hash): StockReservationResult {
            $this->advisoryLock($tenantId.'|reservation|'.$key);
            $existing = StockReservation::query()->where('tenant_id', $tenantId)->where(fn ($query) => $query->where('id', $reservationId)->orWhere('reserve_idempotency_key', $key))->first();
            if ($existing !== null) {
                if (! hash_equals($existing->reserve_command_hash, $hash)) {
                    throw new DomainException('The reservation identity or idempotency key was already used for a different command.');
                }

                return $this->result($existing);
            }

            $balance = $this->lockedBalance($tenantId, $warehouseId, $variantId);
            $reserved = (int) StockReservation::query()->where('tenant_id', $tenantId)->where('warehouse_id', $warehouseId)->where('product_variant_id', $variantId)->where('status', StockReservationStatus::Active)->sum('quantity');
            if ($balance->quantity - $reserved < $quantity) {
                throw new DomainException('Insufficient available stock.');
            }
            $reservation = StockReservation::query()->create([
                'id' => $reservationId, 'tenant_id' => $tenantId, 'warehouse_id' => $warehouseId,
                'product_variant_id' => $variantId, 'quantity' => $quantity, 'status' => StockReservationStatus::Active,
                'reserve_idempotency_key' => $key, 'reserve_command_hash' => $hash,
            ]);

            return $this->result($reservation);
        }, 3);
    }

    public function finalize(string $reservationId, string $idempotencyKey): StockReservationResult
    {
        return $this->transition($reservationId, $idempotencyKey, StockReservationStatus::Finalized);
    }

    public function release(string $reservationId, string $idempotencyKey): StockReservationResult
    {
        return $this->transition($reservationId, $idempotencyKey, StockReservationStatus::Released);
    }

    private function transition(string $reservationId, string $idempotencyKey, StockReservationStatus $target): StockReservationResult
    {
        $tenantId = (string) $this->tenantContext->id();
        $this->assertUuid($reservationId);
        $key = $this->key($idempotencyKey);
        $hash = hash('sha256', implode('|', [$target->value, $reservationId]));

        return DB::transaction(function () use ($tenantId, $reservationId, $key, $hash, $target): StockReservationResult {
            $this->advisoryLock($tenantId.'|reservation-transition|'.$reservationId);
            $keyOwner = StockReservation::query()
                ->where('tenant_id', $tenantId)
                ->where('terminal_idempotency_key', $key)
                ->where('id', '<>', $reservationId)
                ->lockForUpdate()
                ->exists();
            if ($keyOwner) {
                throw new DomainException('The terminal idempotency key was already used for another reservation.');
            }
            $reservation = StockReservation::query()->where('tenant_id', $tenantId)->whereKey($reservationId)->lockForUpdate()->first();
            if ($reservation === null) {
                throw new DomainException('The stock reservation does not exist.');
            }
            if ($reservation->status !== StockReservationStatus::Active) {
                if ($reservation->status === $target && $reservation->terminal_idempotency_key === $key && hash_equals((string) $reservation->terminal_command_hash, $hash)) {
                    return $this->result($reservation);
                }
                throw new DomainException('The reservation is already terminal or the idempotency key conflicts.');
            }

            $movementId = null;
            if ($target === StockReservationStatus::Finalized) {
                $movementId = $this->ledger->postSale($reservation->warehouse_id, $reservation->product_variant_id, $reservation->quantity, 'reservation-finalize:'.$reservationId)->movementId;
            }
            $values = [
                'status' => $target->value, 'terminal_idempotency_key' => $key,
                'terminal_command_hash' => $hash, 'stock_movement_id' => $movementId, 'updated_at' => now(),
                $target === StockReservationStatus::Finalized ? 'finalized_at' : 'released_at' => now(),
            ];
            DB::table('inventory_stock_reservations')->where('tenant_id', $tenantId)->where('id', $reservationId)->update($values);
            $reservation->refresh();

            return $this->result($reservation);
        }, 3);
    }

    private function lockedBalance(string $tenantId, string $warehouseId, string $variantId): StockBalance
    {
        DB::table('inventory_stock_balances')->insertOrIgnore([
            'id' => (string) Str::uuid(), 'tenant_id' => $tenantId, 'warehouse_id' => $warehouseId,
            'product_variant_id' => $variantId, 'quantity' => 0, 'created_at' => now(), 'updated_at' => now(),
        ]);

        return StockBalance::query()->where('tenant_id', $tenantId)->where('warehouse_id', $warehouseId)->where('product_variant_id', $variantId)->lockForUpdate()->firstOrFail();
    }

    private function result(StockReservation $reservation): StockReservationResult
    {
        return new StockReservationResult((string) $reservation->getKey(), $reservation->status->value, $reservation->warehouse_id, $reservation->product_variant_id, $reservation->quantity, $reservation->stock_movement_id);
    }

    private function key(string $key): string
    {
        $key = trim($key);
        if ($key === '' || strlen($key) > 128) {
            throw new InvalidArgumentException('An idempotency key of at most 128 characters is required.');
        }

        return $key;
    }

    private function assertUuid(string $id): void
    {
        if (! Str::isUuid($id)) {
            throw new InvalidArgumentException('A UUID reservation identity is required.');
        }
    }

    private function advisoryLock(string $identity): void
    {
        if (DB::getDriverName() === 'pgsql') {
            DB::select('select pg_advisory_xact_lock(hashtextextended(?, 0))', [$identity]);
        }
    }

    private function activeWarehouseExists(string $tenantId, string $warehouseId): bool
    {
        return DB::table('warehouses')->where('tenant_id', $tenantId)->where('id', $warehouseId)->where('status', 'active')->exists();
    }
}
