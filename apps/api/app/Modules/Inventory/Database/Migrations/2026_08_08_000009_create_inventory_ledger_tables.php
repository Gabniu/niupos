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
        Schema::create('inventory_stock_balances', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->foreignUuid('tenant_id')->constrained('tenants')->cascadeOnDelete();
            $table->uuid('warehouse_id');
            $table->uuid('product_variant_id');
            $table->bigInteger('quantity')->default(0);
            $table->timestampsTz();
            $table->unique(['tenant_id', 'warehouse_id', 'product_variant_id'], 'inventory_balance_identity');
            $table->foreign(['tenant_id', 'warehouse_id'])->references(['tenant_id', 'id'])->on('warehouses')->restrictOnDelete();
            $table->foreign(['tenant_id', 'product_variant_id'])->references(['tenant_id', 'id'])->on('catalogue_product_variants')->restrictOnDelete();
        });
        Schema::create('inventory_stock_movements', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->foreignUuid('tenant_id')->constrained('tenants')->cascadeOnDelete();
            $table->uuid('warehouse_id');
            $table->uuid('product_variant_id');
            $table->string('movement_type', 32);
            $table->bigInteger('quantity_delta');
            $table->bigInteger('balance_after');
            $table->string('idempotency_key', 128);
            $table->char('command_hash', 64);
            $table->timestampsTz();
            $table->unique(['tenant_id', 'id']);
            $table->unique(['tenant_id', 'idempotency_key'], 'inventory_movement_idempotency');
            $table->index(['tenant_id', 'warehouse_id', 'product_variant_id', 'created_at'], 'inventory_movement_lookup');
            $table->foreign(['tenant_id', 'warehouse_id'])->references(['tenant_id', 'id'])->on('warehouses')->restrictOnDelete();
            $table->foreign(['tenant_id', 'product_variant_id'])->references(['tenant_id', 'id'])->on('catalogue_product_variants')->restrictOnDelete();
        });
        if (DB::getDriverName() === 'pgsql') {
            foreach (['inventory_stock_balances', 'inventory_stock_movements'] as $table) {
                DB::unprepared("ALTER TABLE {$table} ENABLE ROW LEVEL SECURITY; ALTER TABLE {$table} FORCE ROW LEVEL SECURITY; CREATE POLICY {$table}_tenant_isolation ON {$table} USING (tenant_id = nullif(current_setting('app.tenant_id', true), '')::uuid) WITH CHECK (tenant_id = nullif(current_setting('app.tenant_id', true), '')::uuid);");
            }
            DB::unprepared(<<<'SQL'
                CREATE OR REPLACE FUNCTION prevent_inventory_stock_movement_mutation() RETURNS trigger AS $$
                BEGIN RAISE EXCEPTION 'inventory stock movements are append-only'; END;
                $$ LANGUAGE plpgsql;
                CREATE TRIGGER inventory_stock_movements_no_update BEFORE UPDATE ON inventory_stock_movements FOR EACH ROW EXECUTE FUNCTION prevent_inventory_stock_movement_mutation();
                CREATE TRIGGER inventory_stock_movements_no_delete BEFORE DELETE ON inventory_stock_movements FOR EACH ROW EXECUTE FUNCTION prevent_inventory_stock_movement_mutation();
                SQL);
        }
    }

    public function down(): void
    {
        if (DB::getDriverName() === 'pgsql') {
            DB::unprepared('DROP FUNCTION IF EXISTS prevent_inventory_stock_movement_mutation() CASCADE');
        }
        Schema::dropIfExists('inventory_stock_movements');
        Schema::dropIfExists('inventory_stock_balances');
    }
};
