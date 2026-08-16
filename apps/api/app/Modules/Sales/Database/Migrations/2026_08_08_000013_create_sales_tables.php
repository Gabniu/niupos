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
        Schema::create('sales', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->foreignUuid('tenant_id')->constrained('tenants')->cascadeOnDelete();
            $table->uuid('shift_id');
            $table->uuid('register_id');
            $table->uuid('warehouse_id');
            $table->foreignUuid('actor_user_id')->constrained('users')->restrictOnDelete();
            $table->string('status', 16);
            $table->char('currency_code', 3);
            $table->bigInteger('net_minor');
            $table->bigInteger('tax_minor');
            $table->bigInteger('gross_minor');
            $table->string('idempotency_key', 128);
            $table->char('command_fingerprint', 64);
            $table->timestampTz('finalized_at');
            $table->timestampsTz();
            $table->unique(['tenant_id', 'id']);
            $table->unique(['tenant_id', 'idempotency_key']);
            $table->foreign(['tenant_id', 'shift_id'])->references(['tenant_id', 'id'])->on('shifts')->restrictOnDelete();
            $table->foreign(['tenant_id', 'register_id'])->references(['tenant_id', 'id'])->on('registers')->restrictOnDelete();
            $table->foreign(['tenant_id', 'warehouse_id'])->references(['tenant_id', 'id'])->on('warehouses')->restrictOnDelete();
            $table->index(['tenant_id', 'finalized_at']);
        });

        Schema::create('sale_lines', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->foreignUuid('tenant_id')->constrained('tenants')->cascadeOnDelete();
            $table->uuid('sale_id');
            $table->uuid('variant_id');
            $table->uuid('reservation_id');
            $table->unsignedInteger('line_number');
            $table->bigInteger('quantity');
            $table->char('currency_code', 3);
            $table->bigInteger('unit_price_minor');
            $table->bigInteger('net_minor');
            $table->bigInteger('tax_minor');
            $table->bigInteger('gross_minor');
            $table->uuid('tax_category_id');
            $table->string('tax_code', 64);
            $table->unsignedInteger('tax_rate_basis_points');
            $table->boolean('tax_inclusive');
            $table->uuid('price_book_id');
            $table->uuid('price_id');
            $table->timestampTz('quoted_at');
            $table->unique(['tenant_id', 'id']);
            $table->unique(['tenant_id', 'sale_id', 'line_number']);
            $table->unique(['tenant_id', 'reservation_id']);
            $table->foreign(['tenant_id', 'sale_id'])->references(['tenant_id', 'id'])->on('sales')->restrictOnDelete();
            $table->foreign(['tenant_id', 'variant_id'])->references(['tenant_id', 'id'])->on('catalogue_product_variants')->restrictOnDelete();
        });

        if (DB::getDriverName() === 'pgsql') {
            foreach (['sales', 'sale_lines'] as $table) {
                DB::unprepared("ALTER TABLE {$table} ENABLE ROW LEVEL SECURITY; ALTER TABLE {$table} FORCE ROW LEVEL SECURITY; CREATE POLICY {$table}_tenant_isolation ON {$table} USING (tenant_id = nullif(current_setting('app.tenant_id', true), '')::uuid) WITH CHECK (tenant_id = nullif(current_setting('app.tenant_id', true), '')::uuid);");
            }
            DB::unprepared(<<<'SQL'
                CREATE OR REPLACE FUNCTION prevent_finalized_sale_mutation() RETURNS trigger AS $$
                BEGIN
                    RAISE EXCEPTION 'finalized sales and lines are immutable';
                END;
                $$ LANGUAGE plpgsql;
                CREATE TRIGGER sales_immutable BEFORE UPDATE OR DELETE ON sales FOR EACH ROW EXECUTE FUNCTION prevent_finalized_sale_mutation();
                CREATE TRIGGER sale_lines_immutable BEFORE UPDATE OR DELETE ON sale_lines FOR EACH ROW EXECUTE FUNCTION prevent_finalized_sale_mutation();
                SQL);
        } elseif (DB::getDriverName() === 'sqlite') {
            foreach (['sales', 'sale_lines'] as $table) {
                DB::unprepared("CREATE TRIGGER {$table}_no_update BEFORE UPDATE ON {$table} BEGIN SELECT RAISE(ABORT, 'finalized sales and lines are immutable'); END");
                DB::unprepared("CREATE TRIGGER {$table}_no_delete BEFORE DELETE ON {$table} BEGIN SELECT RAISE(ABORT, 'finalized sales and lines are immutable'); END");
            }
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('sale_lines');
        Schema::dropIfExists('sales');
        if (DB::getDriverName() === 'pgsql') {
            DB::statement('DROP FUNCTION IF EXISTS prevent_finalized_sale_mutation()');
        }
    }
};
