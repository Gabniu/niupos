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
        Schema::create('receipt_sequences', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->foreignUuid('tenant_id')->constrained('tenants')->cascadeOnDelete();
            $table->uuid('register_id');
            $table->unsignedBigInteger('last_number')->default(0);
            $table->timestampsTz();
            $table->unique(['tenant_id', 'id']);
            $table->unique(['tenant_id', 'register_id']);
            $table->foreign(['tenant_id', 'register_id'])->references(['tenant_id', 'id'])->on('registers')->restrictOnDelete();
        });
        Schema::create('receipts', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->foreignUuid('tenant_id')->constrained('tenants')->cascadeOnDelete();
            $table->uuid('sale_id');
            $table->uuid('shift_id');
            $table->uuid('register_id');
            $table->uuid('seller_id');
            $table->unsignedBigInteger('receipt_number');
            $table->char('currency_code', 3);
            $table->bigInteger('net_minor');
            $table->bigInteger('tax_minor');
            $table->bigInteger('gross_minor');
            $table->string('idempotency_key', 128);
            $table->char('command_fingerprint', 64);
            $table->timestampTz('sale_finalized_at');
            $table->timestampTz('issued_at');
            $table->timestampsTz();
            $table->unique(['tenant_id', 'id']);
            $table->unique(['tenant_id', 'sale_id']);
            $table->unique(['tenant_id', 'idempotency_key']);
            $table->unique(['tenant_id', 'register_id', 'receipt_number']);
            $table->foreign(['tenant_id', 'sale_id'])->references(['tenant_id', 'id'])->on('sales')->restrictOnDelete();
            $table->foreign(['tenant_id', 'shift_id'])->references(['tenant_id', 'id'])->on('shifts')->restrictOnDelete();
            $table->foreign(['tenant_id', 'register_id'])->references(['tenant_id', 'id'])->on('registers')->restrictOnDelete();
        });
        Schema::create('receipt_lines', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->foreignUuid('tenant_id')->constrained('tenants')->cascadeOnDelete();
            $table->uuid('receipt_id');
            $table->unsignedInteger('line_number');
            $table->uuid('variant_id');
            $table->string('description', 255);
            $table->bigInteger('quantity');
            $table->bigInteger('unit_price_minor');
            $table->bigInteger('net_minor');
            $table->bigInteger('tax_minor');
            $table->bigInteger('gross_minor');
            $table->string('tax_code', 64);
            $table->unsignedInteger('tax_rate_basis_points');
            $table->boolean('tax_inclusive');
            $table->unique(['tenant_id', 'id']);
            $table->unique(['tenant_id', 'receipt_id', 'line_number']);
            $table->foreign(['tenant_id', 'receipt_id'])->references(['tenant_id', 'id'])->on('receipts')->restrictOnDelete();
        });
        Schema::create('receipt_delivery_attempts', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->foreignUuid('tenant_id')->constrained('tenants')->cascadeOnDelete();
            $table->uuid('receipt_id');
            $table->string('channel', 16);
            $table->string('outcome', 16);
            $table->timestampTz('attempted_at');
            $table->string('error_code', 64)->nullable();
            $table->unique(['tenant_id', 'id']);
            $table->foreign(['tenant_id', 'receipt_id'])->references(['tenant_id', 'id'])->on('receipts')->restrictOnDelete();
        });
        if (DB::getDriverName() === 'pgsql') {
            foreach (['receipt_sequences', 'receipts', 'receipt_lines', 'receipt_delivery_attempts'] as $table) {
                DB::unprepared("ALTER TABLE {$table} ENABLE ROW LEVEL SECURITY; ALTER TABLE {$table} FORCE ROW LEVEL SECURITY; CREATE POLICY {$table}_tenant_isolation ON {$table} USING (tenant_id = nullif(current_setting('app.tenant_id', true), '')::uuid) WITH CHECK (tenant_id = nullif(current_setting('app.tenant_id', true), '')::uuid);");
            }
            DB::unprepared("CREATE OR REPLACE FUNCTION prevent_receipt_evidence_mutation() RETURNS trigger AS $$ BEGIN RAISE EXCEPTION 'receipt evidence is immutable'; END; $$ LANGUAGE plpgsql; CREATE TRIGGER receipts_immutable BEFORE UPDATE OR DELETE ON receipts FOR EACH ROW EXECUTE FUNCTION prevent_receipt_evidence_mutation(); CREATE TRIGGER receipt_lines_immutable BEFORE UPDATE OR DELETE ON receipt_lines FOR EACH ROW EXECUTE FUNCTION prevent_receipt_evidence_mutation(); CREATE TRIGGER receipt_delivery_attempts_immutable BEFORE UPDATE OR DELETE ON receipt_delivery_attempts FOR EACH ROW EXECUTE FUNCTION prevent_receipt_evidence_mutation();");
        } elseif (DB::getDriverName() === 'sqlite') {
            foreach (['receipts', 'receipt_lines', 'receipt_delivery_attempts'] as $table) {
                DB::unprepared("CREATE TRIGGER {$table}_no_update BEFORE UPDATE ON {$table} BEGIN SELECT RAISE(ABORT, 'receipt evidence is immutable'); END");
                DB::unprepared("CREATE TRIGGER {$table}_no_delete BEFORE DELETE ON {$table} BEGIN SELECT RAISE(ABORT, 'receipt evidence is immutable'); END");
            }
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('receipt_delivery_attempts');
        Schema::dropIfExists('receipt_lines');
        Schema::dropIfExists('receipts');
        Schema::dropIfExists('receipt_sequences');
        if (DB::getDriverName() === 'pgsql') {
            DB::statement('DROP FUNCTION IF EXISTS prevent_receipt_evidence_mutation()');
        }
    }
};
