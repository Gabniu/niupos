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
        Schema::create('audit_events', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->string('event_type', 128);
            $table->uuid('actor_user_id')->nullable();
            $table->json('metadata');
            $table->timestampTz('occurred_at');
            $table->index(['event_type', 'occurred_at']);
            $table->index(['actor_user_id', 'occurred_at']);
        });

        if (DB::getDriverName() === 'pgsql') {
            // CREATE OR REPLACE, not CREATE. `migrate:fresh` drops every TABLE
            // but leaves functions behind -- they are not owned by a table --
            // so a plain CREATE fails on the second run with "function
            // prevent_audit_event_mutation already exists with same argument
            // types", and the whole migration aborts partway through.
            //
            // down() drops it, but migrate:fresh does not call down(); it
            // truncates the schema directly. So the two are not equivalent and
            // this must be idempotent on its own.
            DB::unprepared(<<<'SQL'
                CREATE OR REPLACE FUNCTION prevent_audit_event_mutation() RETURNS trigger AS $$
                BEGIN
                    RAISE EXCEPTION 'audit_events are append-only';
                END;
                $$ LANGUAGE plpgsql;
                CREATE TRIGGER audit_events_append_only
                BEFORE UPDATE OR DELETE ON audit_events
                FOR EACH ROW EXECUTE FUNCTION prevent_audit_event_mutation();
                SQL);
        } elseif (DB::getDriverName() === 'sqlite') {
            DB::unprepared("CREATE TRIGGER audit_events_no_update BEFORE UPDATE ON audit_events BEGIN SELECT RAISE(ABORT, 'audit_events are append-only'); END");
            DB::unprepared("CREATE TRIGGER audit_events_no_delete BEFORE DELETE ON audit_events BEGIN SELECT RAISE(ABORT, 'audit_events are append-only'); END");
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('audit_events');
        if (DB::getDriverName() === 'pgsql') {
            DB::statement('DROP FUNCTION IF EXISTS prevent_audit_event_mutation()');
        }
    }
};
