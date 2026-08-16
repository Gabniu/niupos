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
        Schema::create('onboarding_provisioning_runs', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->foreignUuid('tenant_id')->constrained('tenants')->cascadeOnDelete();
            $table->foreignUuid('initiated_by_user_id')->constrained('users')->restrictOnDelete();
            $table->string('idempotency_key', 128);
            $table->string('command_fingerprint', 64);
            $table->string('status', 32);
            $table->boolean('dry_run')->default(true);
            $table->boolean('approval_required')->default(false);
            $table->json('plan');
            $table->uuid('correlation_id')->unique();
            $table->uuid('approved_by_user_id')->nullable();
            $table->timestampTz('approved_at')->nullable();
            $table->timestampTz('completed_at')->nullable();
            $table->text('failure_message')->nullable();
            $table->timestampsTz();
            $table->unique(['tenant_id', 'idempotency_key']);
            $table->unique(['tenant_id', 'id']);
            $table->index(['tenant_id', 'status']);
        });

        if (DB::getDriverName() === 'pgsql') {
            DB::unprepared(<<<'SQL'
                ALTER TABLE onboarding_provisioning_runs ENABLE ROW LEVEL SECURITY;
                ALTER TABLE onboarding_provisioning_runs FORCE ROW LEVEL SECURITY;
                CREATE POLICY onboarding_provisioning_runs_tenant_isolation ON onboarding_provisioning_runs
                    USING (tenant_id = nullif(current_setting('app.tenant_id', true), '')::uuid)
                    WITH CHECK (tenant_id = nullif(current_setting('app.tenant_id', true), '')::uuid);
                SQL);
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('onboarding_provisioning_runs');
    }
};
