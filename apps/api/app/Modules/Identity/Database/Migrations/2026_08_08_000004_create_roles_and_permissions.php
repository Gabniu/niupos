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
        Schema::create('permissions', function (Blueprint $table): void {
            $table->string('id', 128)->primary();
            $table->string('description');
            $table->timestampsTz();
        });

        Schema::create('roles', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->foreignUuid('tenant_id')->constrained('tenants')->cascadeOnDelete();
            $table->string('name', 64);
            $table->string('description')->nullable();
            $table->timestampsTz();
            $table->unique(['tenant_id', 'name']);
            $table->unique(['tenant_id', 'id']);
        });

        Schema::create('role_permissions', function (Blueprint $table): void {
            $table->foreignUuid('tenant_id')->constrained('tenants')->cascadeOnDelete();
            $table->uuid('role_id');
            $table->string('permission_id', 128);
            $table->foreign(['tenant_id', 'role_id'])->references(['tenant_id', 'id'])->on('roles')->cascadeOnDelete();
            $table->foreign('permission_id')->references('id')->on('permissions')->cascadeOnDelete();
            $table->primary(['tenant_id', 'role_id', 'permission_id']);
        });

        Schema::table('tenant_memberships', function (Blueprint $table): void {
            $table->uuid('role_id')->nullable()->after('user_id');
            $table->foreign(['tenant_id', 'role_id'])->references(['tenant_id', 'id'])->on('roles')->nullOnDelete();
        });

        if (DB::getDriverName() === 'pgsql') {
            DB::statement('ALTER TABLE roles ENABLE ROW LEVEL SECURITY');
            DB::statement('ALTER TABLE roles FORCE ROW LEVEL SECURITY');
            DB::statement("CREATE POLICY roles_tenant_isolation ON roles USING (tenant_id = current_setting('app.tenant_id', true)::uuid) WITH CHECK (tenant_id = current_setting('app.tenant_id', true)::uuid)");
            DB::statement('ALTER TABLE role_permissions ENABLE ROW LEVEL SECURITY');
            DB::statement('ALTER TABLE role_permissions FORCE ROW LEVEL SECURITY');
            DB::statement("CREATE POLICY role_permissions_tenant_isolation ON role_permissions USING (tenant_id = current_setting('app.tenant_id', true)::uuid) WITH CHECK (tenant_id = current_setting('app.tenant_id', true)::uuid)");
        }
    }

    public function down(): void
    {
        Schema::table('tenant_memberships', function (Blueprint $table): void {
            $table->dropForeign(['tenant_id', 'role_id']);
            $table->dropColumn('role_id');
        });
        Schema::dropIfExists('role_permissions');
        Schema::dropIfExists('roles');
        Schema::dropIfExists('permissions');
    }
};
