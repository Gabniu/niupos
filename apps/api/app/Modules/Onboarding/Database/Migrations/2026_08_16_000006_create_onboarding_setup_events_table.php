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
        Schema::create('onboarding_setup_events', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->foreignUuid('tenant_id')->constrained('tenants')->cascadeOnDelete();
            $table->foreignUuid('actor_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->uuid('run_id')->nullable();
            $table->string('type', 96);
            $table->string('status', 32);
            $table->string('message', 255);
            $table->uuid('correlation_id');
            $table->json('metadata')->nullable();
            $table->timestampTz('occurred_at');
            $table->timestampsTz();
            $table->foreign(['tenant_id', 'run_id'])->references(['tenant_id', 'id'])->on('onboarding_provisioning_runs')->nullOnDelete();
            $table->index(['tenant_id', 'occurred_at']);
            $table->index(['tenant_id', 'type']);
        });

        if (DB::getDriverName() === 'pgsql') {
            DB::unprepared(<<<'SQL'
                ALTER TABLE onboarding_setup_events ENABLE ROW LEVEL SECURITY;
                ALTER TABLE onboarding_setup_events FORCE ROW LEVEL SECURITY;
                CREATE POLICY onboarding_setup_events_tenant_isolation ON onboarding_setup_events
                    USING (tenant_id = nullif(current_setting('app.tenant_id', true), '')::uuid)
                    WITH CHECK (tenant_id = nullif(current_setting('app.tenant_id', true), '')::uuid);
                SQL);
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('onboarding_setup_events');
    }
};
