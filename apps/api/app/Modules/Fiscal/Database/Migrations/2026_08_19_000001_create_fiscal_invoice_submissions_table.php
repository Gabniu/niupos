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
        Schema::create('fiscal_invoice_submissions', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->foreignUuid('tenant_id')->constrained('tenants')->cascadeOnDelete();
            $table->uuid('sale_id');
            $table->string('profile', 64);
            $table->char('currency_code', 3);
            $table->bigInteger('net_minor');
            $table->bigInteger('tax_minor');
            $table->bigInteger('gross_minor');
            $table->string('idempotency_key', 128);
            $table->json('payload');
            $table->char('payload_fingerprint', 64);
            $table->string('status', 24);
            $table->unsignedInteger('attempts')->default(0);
            $table->timestampTz('next_attempt_at')->nullable();
            $table->string('provider_reference', 128)->nullable();
            $table->string('last_result_code', 64)->nullable();
            $table->text('last_error')->nullable();
            $table->timestampTz('submitted_at')->nullable();
            $table->timestampsTz();
            $table->unique(['tenant_id', 'sale_id']);
            $table->unique(['tenant_id', 'idempotency_key']);
            $table->foreign(['tenant_id', 'sale_id'])->references(['tenant_id', 'id'])->on('sales')->restrictOnDelete();
            $table->index(['tenant_id', 'status', 'next_attempt_at']);
        });

        if (DB::getDriverName() === 'pgsql') {
            DB::unprepared(<<<'SQL'
                ALTER TABLE fiscal_invoice_submissions ENABLE ROW LEVEL SECURITY;
                ALTER TABLE fiscal_invoice_submissions FORCE ROW LEVEL SECURITY;
                CREATE POLICY fiscal_invoice_submissions_tenant_isolation ON fiscal_invoice_submissions
                    USING (tenant_id = nullif(current_setting('app.tenant_id', true), '')::uuid)
                    WITH CHECK (tenant_id = nullif(current_setting('app.tenant_id', true), '')::uuid);
                SQL);
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('fiscal_invoice_submissions');
    }
};
