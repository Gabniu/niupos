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
        Schema::create('onboarding_provisioning_actions', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->foreignUuid('tenant_id')->constrained('tenants')->cascadeOnDelete();
            $table->uuid('run_id');
            $table->unsignedInteger('sequence');
            $table->string('code', 96);
            $table->string('status', 32);
            $table->boolean('requires_approval')->default(false);
            $table->boolean('reversible')->default(true);
            $table->json('details')->nullable();
            $table->json('result')->nullable();
            $table->text('error_message')->nullable();
            $table->timestampsTz();
            $table->foreign(['tenant_id', 'run_id'])->references(['tenant_id', 'id'])->on('onboarding_provisioning_runs')->cascadeOnDelete();
            $table->unique(['run_id', 'sequence']);
            $table->index(['tenant_id', 'status']);
        });

        if (DB::getDriverName() === 'pgsql') {
            DB::unprepared(<<<'SQL'
                ALTER TABLE onboarding_provisioning_actions ENABLE ROW LEVEL SECURITY;
                ALTER TABLE onboarding_provisioning_actions FORCE ROW LEVEL SECURITY;
                CREATE POLICY onboarding_provisioning_actions_tenant_isolation ON onboarding_provisioning_actions
                    USING (tenant_id = nullif(current_setting('app.tenant_id', true), '')::uuid)
                    WITH CHECK (tenant_id = nullif(current_setting('app.tenant_id', true), '')::uuid);
                SQL);
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('onboarding_provisioning_actions');
    }
};
