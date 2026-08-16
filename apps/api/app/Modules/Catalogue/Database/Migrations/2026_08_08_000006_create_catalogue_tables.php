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
        Schema::create('catalogue_categories', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->foreignUuid('tenant_id')->constrained('tenants')->cascadeOnDelete();
            $table->string('name');
            $table->string('status', 32)->default('active');
            $table->timestampsTz();
            $table->unique(['tenant_id', 'id']);
            $table->index(['tenant_id', 'status']);
        });

        Schema::create('catalogue_units_of_measure', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->foreignUuid('tenant_id')->constrained('tenants')->cascadeOnDelete();
            $table->string('code', 32);
            $table->string('name');
            $table->string('status', 32)->default('active');
            $table->timestampsTz();
            $table->unique(['tenant_id', 'id']);
            $table->unique(['tenant_id', 'code']);
            $table->index(['tenant_id', 'status']);
        });

        Schema::create('catalogue_products', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->foreignUuid('tenant_id')->constrained('tenants')->cascadeOnDelete();
            $table->uuid('category_id')->nullable();
            $table->string('name');
            $table->string('status', 32)->default('active');
            $table->timestampsTz();
            $table->unique(['tenant_id', 'id']);
            $table->index(['tenant_id', 'status']);
            $table->foreign(['tenant_id', 'category_id'])->references(['tenant_id', 'id'])
                ->on('catalogue_categories')->restrictOnDelete();
        });

        Schema::create('catalogue_product_variants', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->foreignUuid('tenant_id')->constrained('tenants')->cascadeOnDelete();
            $table->uuid('product_id');
            $table->uuid('unit_of_measure_id');
            $table->string('name');
            $table->string('sku');
            $table->string('normalized_sku');
            $table->string('status', 32)->default('active');
            $table->timestampsTz();
            $table->unique(['tenant_id', 'id']);
            $table->unique(['tenant_id', 'normalized_sku']);
            $table->index(['tenant_id', 'status']);
            $table->foreign(['tenant_id', 'product_id'])->references(['tenant_id', 'id'])
                ->on('catalogue_products')->cascadeOnDelete();
            $table->foreign(['tenant_id', 'unit_of_measure_id'])->references(['tenant_id', 'id'])
                ->on('catalogue_units_of_measure')->restrictOnDelete();
        });

        Schema::create('catalogue_barcodes', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->foreignUuid('tenant_id')->constrained('tenants')->cascadeOnDelete();
            $table->uuid('product_variant_id');
            $table->string('value');
            $table->string('normalized_value');
            $table->string('status', 32)->default('active');
            $table->timestampsTz();
            $table->unique(['tenant_id', 'normalized_value']);
            $table->index(['tenant_id', 'product_variant_id']);
            $table->foreign(['tenant_id', 'product_variant_id'])->references(['tenant_id', 'id'])
                ->on('catalogue_product_variants')->cascadeOnDelete();
        });

        if (DB::getDriverName() === 'pgsql') {
            foreach (['catalogue_categories', 'catalogue_units_of_measure', 'catalogue_products', 'catalogue_product_variants', 'catalogue_barcodes'] as $table) {
                DB::unprepared("ALTER TABLE {$table} ENABLE ROW LEVEL SECURITY; ALTER TABLE {$table} FORCE ROW LEVEL SECURITY; CREATE POLICY {$table}_tenant_isolation ON {$table} USING (tenant_id = nullif(current_setting('app.tenant_id', true), '')::uuid) WITH CHECK (tenant_id = nullif(current_setting('app.tenant_id', true), '')::uuid);");
            }
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('catalogue_barcodes');
        Schema::dropIfExists('catalogue_product_variants');
        Schema::dropIfExists('catalogue_products');
        Schema::dropIfExists('catalogue_units_of_measure');
        Schema::dropIfExists('catalogue_categories');
    }
};
