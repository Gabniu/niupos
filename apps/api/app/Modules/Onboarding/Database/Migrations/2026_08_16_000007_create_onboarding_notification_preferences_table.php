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
        Schema::create('onboarding_notification_preferences', function (Blueprint $table): void {
            $table->uuid('tenant_id')->primary();
            $table->boolean('in_app_enabled')->default(true);
            $table->boolean('email_enabled')->default(false);
            $table->boolean('sms_enabled')->default(false);
            $table->boolean('push_enabled')->default(false);
            $table->string('quiet_start', 5)->nullable();
            $table->string('quiet_end', 5)->nullable();
            $table->timestampsTz();
            $table->foreign('tenant_id')->references('id')->on('tenants')->cascadeOnDelete();
        });

        if (DB::getDriverName() === 'pgsql') {
            DB::unprepared(<<<'SQL'
                ALTER TABLE onboarding_notification_preferences ENABLE ROW LEVEL SECURITY;
                ALTER TABLE onboarding_notification_preferences FORCE ROW LEVEL SECURITY;
                CREATE POLICY onboarding_notification_preferences_tenant_isolation ON onboarding_notification_preferences
                    USING (tenant_id = nullif(current_setting('app.tenant_id', true), '')::uuid)
                    WITH CHECK (tenant_id = nullif(current_setting('app.tenant_id', true), '')::uuid);
                SQL);
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('onboarding_notification_preferences');
    }
};
