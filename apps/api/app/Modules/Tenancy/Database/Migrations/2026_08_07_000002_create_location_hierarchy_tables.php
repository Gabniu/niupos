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
        Schema::create('companies', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->foreignUuid('tenant_id')->constrained('tenants')->cascadeOnDelete();
            $table->string('name');
            $table->string('status', 32)->default('active');
            $table->timestampsTz();
            $table->unique(['tenant_id', 'id']);
            $table->unique(['tenant_id', 'name']);
            $table->index(['tenant_id', 'status']);
        });

        Schema::create('branches', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->foreignUuid('tenant_id')->constrained('tenants')->cascadeOnDelete();
            $table->uuid('company_id');
            $table->string('code', 64);
            $table->string('name');
            $table->string('status', 32)->default('active');
            $table->timestampsTz();
            $table->unique(['tenant_id', 'id']);
            $table->unique(['tenant_id', 'company_id', 'code']);
            $table->foreign(['tenant_id', 'company_id'])->references(['tenant_id', 'id'])->on('companies')->cascadeOnDelete();
            $table->index(['tenant_id', 'status']);
        });

        Schema::create('warehouses', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->foreignUuid('tenant_id')->constrained('tenants')->cascadeOnDelete();
            $table->uuid('branch_id');
            $table->string('code', 64);
            $table->string('name');
            $table->string('status', 32)->default('active');
            $table->timestampsTz();
            $table->unique(['tenant_id', 'id']);
            $table->unique(['tenant_id', 'branch_id', 'code']);
            $table->foreign(['tenant_id', 'branch_id'])->references(['tenant_id', 'id'])->on('branches')->cascadeOnDelete();
            $table->index(['tenant_id', 'status']);
        });

        if (DB::getDriverName() === 'pgsql') {
            foreach (['companies', 'branches', 'warehouses'] as $table) {
                DB::unprepared(<<<SQL
                    ALTER TABLE {$table} ENABLE ROW LEVEL SECURITY;
                    ALTER TABLE {$table} FORCE ROW LEVEL SECURITY;
                    CREATE POLICY {$table}_tenant_isolation ON {$table}
                        USING (tenant_id = nullif(current_setting('app.tenant_id', true), '')::uuid)
                        WITH CHECK (tenant_id = nullif(current_setting('app.tenant_id', true), '')::uuid);
                    SQL);
            }
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('warehouses');
        Schema::dropIfExists('branches');
        Schema::dropIfExists('companies');
    }
};
