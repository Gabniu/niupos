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
        Schema::create('payment_attempts', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->foreignUuid('tenant_id')->constrained('tenants')->cascadeOnDelete();
            $table->uuid('sale_id');
            $table->foreignUuid('actor_user_id')->constrained('users')->restrictOnDelete();
            $table->string('method', 16);
            $table->string('status', 16);
            $table->bigInteger('amount_minor');
            $table->char('currency_code', 3);
            $table->string('idempotency_key', 128);
            $table->char('command_fingerprint', 64);
            $table->string('provider_reference', 128)->nullable();
            $table->json('provider_metadata');
            $table->char('provider_result_fingerprint', 64)->nullable();
            $table->timestampTz('completed_at')->nullable();
            $table->timestampsTz();
            $table->unique(['tenant_id', 'id']);
            $table->unique(['tenant_id', 'idempotency_key']);
            $table->foreign(['tenant_id', 'sale_id'])->references(['tenant_id', 'id'])->on('sales')->restrictOnDelete();
            $table->index(['tenant_id', 'sale_id', 'status']);
        });

        Schema::create('payment_allocations', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->foreignUuid('tenant_id')->constrained('tenants')->cascadeOnDelete();
            $table->uuid('payment_attempt_id');
            $table->uuid('sale_id');
            $table->bigInteger('amount_minor');
            $table->char('currency_code', 3);
            $table->timestampsTz();
            $table->unique(['tenant_id', 'id']);
            $table->unique(['tenant_id', 'payment_attempt_id']);
            $table->foreign(['tenant_id', 'payment_attempt_id'])->references(['tenant_id', 'id'])->on('payment_attempts')->restrictOnDelete();
            $table->foreign(['tenant_id', 'sale_id'])->references(['tenant_id', 'id'])->on('sales')->restrictOnDelete();
            $table->index(['tenant_id', 'sale_id']);
        });

        if (DB::getDriverName() === 'pgsql') {
            foreach (['payment_attempts', 'payment_allocations'] as $table) {
                DB::unprepared("ALTER TABLE {$table} ENABLE ROW LEVEL SECURITY; ALTER TABLE {$table} FORCE ROW LEVEL SECURITY; CREATE POLICY {$table}_tenant_isolation ON {$table} USING (tenant_id = nullif(current_setting('app.tenant_id', true), '')::uuid) WITH CHECK (tenant_id = nullif(current_setting('app.tenant_id', true), '')::uuid);");
            }
            DB::unprepared(<<<'SQL'
                CREATE OR REPLACE FUNCTION enforce_payment_attempt_transition() RETURNS trigger AS $$
                BEGIN
                    IF TG_OP = 'DELETE' THEN RAISE EXCEPTION 'payment attempts cannot be deleted'; END IF;
                    IF OLD.status <> 'pending' OR NEW.status NOT IN ('succeeded', 'failed')
                       OR ROW(NEW.tenant_id, NEW.sale_id, NEW.actor_user_id, NEW.method, NEW.amount_minor, NEW.currency_code, NEW.idempotency_key, NEW.command_fingerprint, NEW.provider_metadata)
                          IS DISTINCT FROM ROW(OLD.tenant_id, OLD.sale_id, OLD.actor_user_id, OLD.method, OLD.amount_minor, OLD.currency_code, OLD.idempotency_key, OLD.command_fingerprint, OLD.provider_metadata) THEN
                        RAISE EXCEPTION 'invalid payment attempt transition';
                    END IF;
                    RETURN NEW;
                END;
                $$ LANGUAGE plpgsql;
                CREATE TRIGGER payment_attempts_controlled BEFORE UPDATE OR DELETE ON payment_attempts FOR EACH ROW EXECUTE FUNCTION enforce_payment_attempt_transition();
                CREATE OR REPLACE FUNCTION prevent_payment_allocation_mutation() RETURNS trigger AS $$
                BEGIN RAISE EXCEPTION 'payment allocations are append-only'; END;
                $$ LANGUAGE plpgsql;
                CREATE TRIGGER payment_allocations_immutable BEFORE UPDATE OR DELETE ON payment_allocations FOR EACH ROW EXECUTE FUNCTION prevent_payment_allocation_mutation();
                SQL);
        } elseif (DB::getDriverName() === 'sqlite') {
            DB::unprepared("CREATE TRIGGER payment_attempts_no_delete BEFORE DELETE ON payment_attempts BEGIN SELECT RAISE(ABORT, 'payment attempts cannot be deleted'); END");
            DB::unprepared("CREATE TRIGGER payment_attempts_controlled_update BEFORE UPDATE ON payment_attempts WHEN OLD.status <> 'pending' OR NEW.status NOT IN ('succeeded','failed') OR NEW.tenant_id <> OLD.tenant_id OR NEW.sale_id <> OLD.sale_id OR NEW.actor_user_id <> OLD.actor_user_id OR NEW.method <> OLD.method OR NEW.amount_minor <> OLD.amount_minor OR NEW.currency_code <> OLD.currency_code OR NEW.idempotency_key <> OLD.idempotency_key OR NEW.command_fingerprint <> OLD.command_fingerprint OR NEW.provider_metadata <> OLD.provider_metadata BEGIN SELECT RAISE(ABORT, 'invalid payment attempt transition'); END");
            foreach (['UPDATE', 'DELETE'] as $operation) {
                DB::unprepared('CREATE TRIGGER payment_allocations_no_'.strtolower($operation)." BEFORE {$operation} ON payment_allocations BEGIN SELECT RAISE(ABORT, 'payment allocations are append-only'); END");
            }
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('payment_allocations');
        Schema::dropIfExists('payment_attempts');
        if (DB::getDriverName() === 'pgsql') {
            DB::unprepared('DROP FUNCTION IF EXISTS prevent_payment_allocation_mutation() CASCADE');
            DB::unprepared('DROP FUNCTION IF EXISTS enforce_payment_attempt_transition() CASCADE');
        }
    }
};
