<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('inventory_stock_reservations', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->foreignUuid('tenant_id')->constrained('tenants')->cascadeOnDelete();
            $table->uuid('warehouse_id');
            $table->uuid('product_variant_id');
            $table->unsignedBigInteger('quantity');
            $table->string('status', 16);
            $table->string('reserve_idempotency_key', 128);
            $table->char('reserve_command_hash', 64);
            $table->string('terminal_idempotency_key', 128)->nullable();
            $table->char('terminal_command_hash', 64)->nullable();
            $table->uuid('stock_movement_id')->nullable();
            $table->timestampTz('finalized_at')->nullable();
            $table->timestampTz('released_at')->nullable();
            $table->timestampsTz();
            $table->unique(['tenant_id', 'id']);
            $table->unique(['tenant_id', 'reserve_idempotency_key'], 'inventory_reservation_idempotency');
            $table->unique(['tenant_id', 'terminal_idempotency_key'], 'inventory_reservation_terminal_idempotency');
            $table->index(['tenant_id', 'warehouse_id', 'product_variant_id', 'status'], 'inventory_active_reservation_lookup');
            $table->foreign(['tenant_id', 'warehouse_id'])->references(['tenant_id', 'id'])->on('warehouses')->restrictOnDelete();
            $table->foreign(['tenant_id', 'product_variant_id'])->references(['tenant_id', 'id'])->on('catalogue_product_variants')->restrictOnDelete();
            $table->foreign(['tenant_id', 'stock_movement_id'])->references(['tenant_id', 'id'])->on('inventory_stock_movements')->restrictOnDelete();
        });

        if (DB::getDriverName() === 'pgsql') {
            DB::unprepared(<<<'SQL'
                ALTER TABLE inventory_stock_reservations ENABLE ROW LEVEL SECURITY;
                ALTER TABLE inventory_stock_reservations FORCE ROW LEVEL SECURITY;
                CREATE POLICY inventory_stock_reservations_tenant_isolation ON inventory_stock_reservations
                    USING (tenant_id = nullif(current_setting('app.tenant_id', true), '')::uuid)
                    WITH CHECK (tenant_id = nullif(current_setting('app.tenant_id', true), '')::uuid);
                CREATE OR REPLACE FUNCTION enforce_inventory_reservation_transition() RETURNS trigger AS $$
                BEGIN
                    IF OLD.tenant_id <> NEW.tenant_id OR OLD.id <> NEW.id OR OLD.warehouse_id <> NEW.warehouse_id
                       OR OLD.product_variant_id <> NEW.product_variant_id OR OLD.quantity <> NEW.quantity
                       OR OLD.reserve_idempotency_key <> NEW.reserve_idempotency_key OR OLD.reserve_command_hash <> NEW.reserve_command_hash THEN
                        RAISE EXCEPTION 'inventory reservation facts are immutable';
                    END IF;
                    IF OLD.status <> 'active' OR NEW.status NOT IN ('finalized', 'released') THEN
                        RAISE EXCEPTION 'invalid inventory reservation transition';
                    END IF;
                    RETURN NEW;
                END;
                $$ LANGUAGE plpgsql;
                CREATE TRIGGER inventory_stock_reservations_transition_guard BEFORE UPDATE ON inventory_stock_reservations
                    FOR EACH ROW EXECUTE FUNCTION enforce_inventory_reservation_transition();
                CREATE TRIGGER inventory_stock_reservations_no_delete BEFORE DELETE ON inventory_stock_reservations
                    FOR EACH ROW EXECUTE FUNCTION prevent_inventory_stock_movement_mutation();
                SQL);
        }
    }

    public function down(): void
    {
        if (DB::getDriverName() === 'pgsql') {
            DB::unprepared('DROP FUNCTION IF EXISTS enforce_inventory_reservation_transition() CASCADE');
        }
        Schema::dropIfExists('inventory_stock_reservations');
    }
};
