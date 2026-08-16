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
        Schema::create('channel_registrations', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->foreignUuid('tenant_id')->constrained('tenants')->cascadeOnDelete();
            $table->string('channel', 16);
            $table->string('audience', 32)->default('customer');
            $table->string('environment', 16);
            $table->string('display_name', 160);
            $table->uuid('client_id')->unique();
            $table->string('status', 32)->default('draft');
            $table->json('configuration')->nullable();
            $table->json('redirect_uris')->nullable();
            $table->timestampsTz();
            $table->unique(['tenant_id', 'channel', 'environment']);
            $table->index(['tenant_id', 'status']);
        });

        if (DB::getDriverName() === 'pgsql') {
            DB::unprepared(<<<'SQL'
                ALTER TABLE channel_registrations ENABLE ROW LEVEL SECURITY;
                ALTER TABLE channel_registrations FORCE ROW LEVEL SECURITY;
                CREATE POLICY channel_registrations_tenant_isolation ON channel_registrations
                    USING (tenant_id = nullif(current_setting('app.tenant_id', true), '')::uuid)
                    WITH CHECK (tenant_id = nullif(current_setting('app.tenant_id', true), '')::uuid);
                SQL);
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('channel_registrations');
    }
};
