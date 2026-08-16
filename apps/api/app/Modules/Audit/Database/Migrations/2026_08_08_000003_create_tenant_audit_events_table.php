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
        Schema::create('tenant_audit_events', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->foreignUuid('tenant_id')->constrained('tenants')->cascadeOnDelete();
            $table->string('event_type', 128);
            $table->uuid('actor_user_id')->nullable();
            $table->json('metadata');
            $table->timestampTz('occurred_at');
            $table->index(['tenant_id', 'event_type', 'occurred_at']);
            $table->index(['tenant_id', 'actor_user_id', 'occurred_at']);
        });

        if (DB::getDriverName() === 'pgsql') {
            DB::statement('ALTER TABLE tenant_audit_events ENABLE ROW LEVEL SECURITY');
            DB::statement('ALTER TABLE tenant_audit_events FORCE ROW LEVEL SECURITY');
            DB::statement("CREATE POLICY tenant_audit_events_isolation ON tenant_audit_events USING (tenant_id = current_setting('app.tenant_id', true)::uuid) WITH CHECK (tenant_id = current_setting('app.tenant_id', true)::uuid)");
            DB::unprepared(<<<'SQL'
                CREATE TRIGGER tenant_audit_events_append_only
                BEFORE UPDATE OR DELETE ON tenant_audit_events
                FOR EACH ROW EXECUTE FUNCTION prevent_audit_event_mutation();
                SQL);
        } elseif (DB::getDriverName() === 'sqlite') {
            DB::unprepared("CREATE TRIGGER tenant_audit_events_no_update BEFORE UPDATE ON tenant_audit_events BEGIN SELECT RAISE(ABORT, 'tenant_audit_events are append-only'); END");
            DB::unprepared("CREATE TRIGGER tenant_audit_events_no_delete BEFORE DELETE ON tenant_audit_events BEGIN SELECT RAISE(ABORT, 'tenant_audit_events are append-only'); END");
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('tenant_audit_events');
    }
};
