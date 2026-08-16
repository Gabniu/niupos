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
        Schema::table('onboarding_setup_notifications', function (Blueprint $table): void {
            $table->unique(['tenant_id', 'id'], 'onboarding_setup_notifications_tenant_id_id_unique');
        });

        Schema::create('onboarding_notification_deliveries', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->uuid('tenant_id');
            $table->uuid('notification_id');
            $table->foreignUuid('recipient_user_id')->constrained('users')->cascadeOnDelete();
            $table->string('channel', 16);
            $table->string('status', 32);
            $table->string('blocked_reason', 255)->nullable();
            $table->timestampTz('sent_at')->nullable();
            $table->timestampsTz();
            $table->foreign('tenant_id')->references('id')->on('tenants')->cascadeOnDelete();
            $table->foreign(['tenant_id', 'notification_id'])->references(['tenant_id', 'id'])->on('onboarding_setup_notifications')->cascadeOnDelete();
            $table->unique(['tenant_id', 'notification_id', 'channel']);
            $table->index(['tenant_id', 'status', 'channel']);
        });

        if (DB::getDriverName() === 'pgsql') {
            DB::unprepared(<<<'SQL'
                ALTER TABLE onboarding_notification_deliveries ENABLE ROW LEVEL SECURITY;
                ALTER TABLE onboarding_notification_deliveries FORCE ROW LEVEL SECURITY;
                CREATE POLICY onboarding_notification_deliveries_tenant_isolation ON onboarding_notification_deliveries
                    USING (tenant_id = nullif(current_setting('app.tenant_id', true), '')::uuid)
                    WITH CHECK (tenant_id = nullif(current_setting('app.tenant_id', true), '')::uuid);
                SQL);
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('onboarding_notification_deliveries');
        Schema::table('onboarding_setup_notifications', function (Blueprint $table): void {
            $table->dropUnique('onboarding_setup_notifications_tenant_id_id_unique');
        });
    }
};
