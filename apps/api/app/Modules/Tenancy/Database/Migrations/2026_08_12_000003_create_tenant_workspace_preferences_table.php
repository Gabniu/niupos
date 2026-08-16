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
        Schema::create('tenant_workspace_preferences', function (Blueprint $table): void {
            $table->uuid('tenant_id')->primary();
            $table->boolean('side_panel_visible')->default(true);
            $table->boolean('kiosk_mode')->default(false);
            $table->timestampsTz();
            $table->foreign('tenant_id')->references('id')->on('tenants')->cascadeOnDelete();
        });

        if (DB::getDriverName() === 'pgsql') {
            DB::unprepared(<<<'SQL'
                ALTER TABLE tenant_workspace_preferences ENABLE ROW LEVEL SECURITY;
                ALTER TABLE tenant_workspace_preferences FORCE ROW LEVEL SECURITY;
                CREATE POLICY tenant_workspace_preferences_tenant_isolation ON tenant_workspace_preferences
                    USING (tenant_id = nullif(current_setting('app.tenant_id', true), '')::uuid)
                    WITH CHECK (tenant_id = nullif(current_setting('app.tenant_id', true), '')::uuid);
                SQL);
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('tenant_workspace_preferences');
    }
};
