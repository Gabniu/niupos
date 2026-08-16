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
        Schema::create('registers', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->foreignUuid('tenant_id')->constrained('tenants')->cascadeOnDelete();
            $table->uuid('branch_id');
            $table->string('code', 64);
            $table->string('name');
            $table->string('status', 32)->default('active');
            $table->timestampsTz();
            $table->unique(['tenant_id', 'id']);
            $table->unique(['tenant_id', 'branch_id', 'code']);
            $table->foreign(['tenant_id', 'branch_id'])->references(['tenant_id', 'id'])->on('branches')->restrictOnDelete();
            $table->index(['tenant_id', 'status']);
        });

        Schema::create('devices', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->foreignUuid('tenant_id')->constrained('tenants')->cascadeOnDelete();
            $table->uuid('register_id');
            $table->uuid('public_id')->unique();
            $table->string('display_name');
            $table->string('status', 32)->default('pending_enrollment');
            $table->char('enrollment_token_digest', 64)->nullable()->unique();
            $table->timestampTz('enrollment_expires_at')->nullable();
            $table->timestampTz('enrollment_consumed_at')->nullable();
            $table->timestampTz('last_seen_at')->nullable();
            $table->timestampsTz();
            $table->unique(['tenant_id', 'id']);
            $table->foreign(['tenant_id', 'register_id'])->references(['tenant_id', 'id'])->on('registers')->restrictOnDelete();
            $table->index(['tenant_id', 'status']);
            $table->index(['tenant_id', 'public_id']);
        });

        if (DB::getDriverName() === 'pgsql') {
            foreach (['registers', 'devices'] as $table) {
                DB::unprepared("ALTER TABLE {$table} ENABLE ROW LEVEL SECURITY; ALTER TABLE {$table} FORCE ROW LEVEL SECURITY; CREATE POLICY {$table}_tenant_isolation ON {$table} USING (tenant_id = nullif(current_setting('app.tenant_id', true), '')::uuid) WITH CHECK (tenant_id = nullif(current_setting('app.tenant_id', true), '')::uuid);");
            }
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('devices');
        Schema::dropIfExists('registers');
    }
};
