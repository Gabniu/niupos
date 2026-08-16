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
        Schema::create('sync_changes', function (Blueprint $table): void {
            $table->bigIncrements('cursor');
            $table->foreignUuid('tenant_id')->constrained('tenants')->cascadeOnDelete();
            $table->string('entity_type', 128);
            $table->string('entity_id', 128);
            $table->string('operation', 32);
            $table->json('payload');
            $table->timestampTz('occurred_at');
            $table->unique(['tenant_id', 'cursor']);
            $table->index(['tenant_id', 'cursor']);
        });

        Schema::create('sync_device_cursors', function (Blueprint $table): void {
            $table->foreignUuid('tenant_id')->constrained('tenants')->cascadeOnDelete();
            $table->uuid('device_id');
            $table->unsignedBigInteger('last_pulled_cursor')->default(0);
            $table->timestampTz('updated_at');
            $table->primary(['tenant_id', 'device_id']);
            $table->foreign(['tenant_id', 'device_id'])->references(['tenant_id', 'id'])->on('devices')->cascadeOnDelete();
        });

        Schema::create('sync_command_inbox', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->foreignUuid('tenant_id')->constrained('tenants')->cascadeOnDelete();
            $table->uuid('device_id');
            $table->uuid('command_id');
            $table->string('protocol_version', 16);
            $table->string('command_type', 128);
            $table->timestampTz('client_occurred_at');
            $table->json('payload');
            $table->char('payload_fingerprint', 64);
            $table->string('status', 32)->default('received');
            $table->unsignedInteger('attempt_count')->default(0);
            $table->string('result_code', 128)->nullable();
            $table->text('result_message')->nullable();
            $table->timestampTz('last_attempted_at')->nullable();
            $table->timestampTz('completed_at')->nullable();
            $table->timestampsTz();
            $table->unique(['tenant_id', 'device_id', 'command_id']);
            $table->unique(['tenant_id', 'device_id', 'id']);
            $table->foreign(['tenant_id', 'device_id'])->references(['tenant_id', 'id'])->on('devices')->cascadeOnDelete();
            $table->index(['tenant_id', 'device_id', 'status']);
        });

        Schema::create('sync_conflicts', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->foreignUuid('tenant_id')->constrained('tenants')->cascadeOnDelete();
            $table->uuid('device_id');
            $table->uuid('command_inbox_id');
            $table->string('conflict_code', 128);
            $table->text('message');
            $table->json('evidence');
            $table->timestampTz('detected_at');
            $table->unique(['tenant_id', 'device_id', 'command_inbox_id']);
            $table->foreign(['tenant_id', 'device_id', 'command_inbox_id'])->references(['tenant_id', 'device_id', 'id'])->on('sync_command_inbox')->cascadeOnDelete();
        });

        if (DB::getDriverName() === 'pgsql') {
            foreach (['sync_changes', 'sync_device_cursors', 'sync_command_inbox', 'sync_conflicts'] as $table) {
                DB::unprepared("ALTER TABLE {$table} ENABLE ROW LEVEL SECURITY; ALTER TABLE {$table} FORCE ROW LEVEL SECURITY; CREATE POLICY {$table}_tenant_isolation ON {$table} USING (tenant_id = nullif(current_setting('app.tenant_id', true), '')::uuid) WITH CHECK (tenant_id = nullif(current_setting('app.tenant_id', true), '')::uuid);");
            }
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('sync_conflicts');
        Schema::dropIfExists('sync_command_inbox');
        Schema::dropIfExists('sync_device_cursors');
        Schema::dropIfExists('sync_changes');
    }
};
