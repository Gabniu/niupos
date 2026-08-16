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
        Schema::create('shifts', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->foreignUuid('tenant_id')->constrained('tenants')->cascadeOnDelete();
            $table->uuid('register_id');
            $table->foreignUuid('opening_user_id')->constrained('users')->restrictOnDelete();
            $table->foreignUuid('closing_user_id')->nullable()->constrained('users')->restrictOnDelete();
            $table->string('status', 16);
            $table->char('currency', 3);
            $table->bigInteger('opening_float_minor');
            $table->bigInteger('expected_cash_minor');
            $table->bigInteger('counted_cash_minor')->nullable();
            $table->bigInteger('variance_minor')->nullable();
            $table->timestampTz('opened_at');
            $table->timestampTz('closed_at')->nullable();
            $table->string('idempotency_key', 128);
            $table->timestampsTz();
            $table->unique(['tenant_id', 'id']);
            $table->unique(['tenant_id', 'idempotency_key']);
            $table->foreign(['tenant_id', 'register_id'])->references(['tenant_id', 'id'])->on('registers')->restrictOnDelete();
            $table->index(['tenant_id', 'register_id', 'status']);
        });

        Schema::create('cash_movements', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->foreignUuid('tenant_id')->constrained('tenants')->cascadeOnDelete();
            $table->uuid('shift_id');
            $table->string('type', 16);
            $table->bigInteger('amount_minor');
            $table->char('currency', 3);
            $table->string('reason', 500);
            $table->foreignUuid('actor_user_id')->constrained('users')->restrictOnDelete();
            $table->string('idempotency_key', 128);
            $table->timestampTz('occurred_at');
            $table->unique(['tenant_id', 'id']);
            $table->unique(['tenant_id', 'idempotency_key']);
            $table->foreign(['tenant_id', 'shift_id'])->references(['tenant_id', 'id'])->on('shifts')->restrictOnDelete();
            $table->index(['tenant_id', 'shift_id', 'occurred_at']);
        });

        $driver = DB::getDriverName();
        if (in_array($driver, ['pgsql', 'sqlite'], true)) {
            DB::statement("CREATE UNIQUE INDEX shifts_one_open_per_register ON shifts (tenant_id, register_id) WHERE status = 'open'");
        }

        if ($driver === 'pgsql') {
            foreach (['shifts', 'cash_movements'] as $table) {
                DB::unprepared("ALTER TABLE {$table} ENABLE ROW LEVEL SECURITY; ALTER TABLE {$table} FORCE ROW LEVEL SECURITY; CREATE POLICY {$table}_tenant_isolation ON {$table} USING (tenant_id = nullif(current_setting('app.tenant_id', true), '')::uuid) WITH CHECK (tenant_id = nullif(current_setting('app.tenant_id', true), '')::uuid);");
            }
            DB::unprepared(<<<'SQL'
                CREATE OR REPLACE FUNCTION prevent_cash_movement_mutation() RETURNS trigger AS $$
                BEGIN
                    RAISE EXCEPTION 'cash_movements are append-only';
                END;
                $$ LANGUAGE plpgsql;
                CREATE TRIGGER cash_movements_append_only
                BEFORE UPDATE OR DELETE ON cash_movements
                FOR EACH ROW EXECUTE FUNCTION prevent_cash_movement_mutation();
                SQL);
        } elseif ($driver === 'sqlite') {
            DB::unprepared("CREATE TRIGGER cash_movements_no_update BEFORE UPDATE ON cash_movements BEGIN SELECT RAISE(ABORT, 'cash_movements are append-only'); END");
            DB::unprepared("CREATE TRIGGER cash_movements_no_delete BEFORE DELETE ON cash_movements BEGIN SELECT RAISE(ABORT, 'cash_movements are append-only'); END");
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('cash_movements');
        Schema::dropIfExists('shifts');
        if (DB::getDriverName() === 'pgsql') {
            DB::statement('DROP FUNCTION IF EXISTS prevent_cash_movement_mutation()');
        }
    }
};
