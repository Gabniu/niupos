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
        // The setup-events table predates this notification FK and only has a
        // primary key on id. PostgreSQL requires the referenced tenant/id pair
        // to be explicitly unique for a tenant-safe composite foreign key.
        Schema::table('onboarding_setup_events', function (Blueprint $table): void {
            $table->unique(['tenant_id', 'id'], 'onboarding_setup_events_tenant_id_id_unique');
        });

        Schema::create('onboarding_setup_notifications', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->uuid('tenant_id');
            $table->foreignUuid('recipient_user_id')->constrained('users')->cascadeOnDelete();
            $table->uuid('event_id');
            $table->uuid('run_id')->nullable();
            $table->string('type', 96);
            $table->string('title', 160);
            $table->text('message');
            $table->timestampTz('read_at')->nullable();
            $table->timestampsTz();
            $table->foreign('tenant_id')->references('id')->on('tenants')->cascadeOnDelete();
            $table->foreign(['tenant_id', 'event_id'])->references(['tenant_id', 'id'])->on('onboarding_setup_events')->cascadeOnDelete();
            $table->foreign(['tenant_id', 'run_id'])->references(['tenant_id', 'id'])->on('onboarding_provisioning_runs')->nullOnDelete();
            $table->unique(['tenant_id', 'event_id', 'recipient_user_id']);
            $table->index(['tenant_id', 'recipient_user_id', 'read_at']);
        });

        if (DB::getDriverName() === 'pgsql') {
            DB::unprepared(<<<'SQL'
                ALTER TABLE onboarding_setup_notifications ENABLE ROW LEVEL SECURITY;
                ALTER TABLE onboarding_setup_notifications FORCE ROW LEVEL SECURITY;
                CREATE POLICY onboarding_setup_notifications_tenant_isolation ON onboarding_setup_notifications
                    USING (tenant_id = nullif(current_setting('app.tenant_id', true), '')::uuid)
                    WITH CHECK (tenant_id = nullif(current_setting('app.tenant_id', true), '')::uuid);
                SQL);
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('onboarding_setup_notifications');
        Schema::table('onboarding_setup_events', function (Blueprint $table): void {
            $table->dropUnique('onboarding_setup_events_tenant_id_id_unique');
        });
    }
};
