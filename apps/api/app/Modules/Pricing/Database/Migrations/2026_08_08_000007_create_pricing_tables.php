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
        Schema::create('pricing_tax_categories', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->foreignUuid('tenant_id')->constrained('tenants')->cascadeOnDelete();
            $table->string('code', 32);
            $table->unsignedInteger('rate_basis_points');
            $table->boolean('is_inclusive');
            $table->string('status', 32)->default('active');
            $table->timestampsTz();
            $table->unique(['tenant_id', 'id']);
            $table->unique(['tenant_id', 'code']);
            $table->index(['tenant_id', 'status']);
        });
        Schema::create('pricing_price_books', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->foreignUuid('tenant_id')->constrained('tenants')->cascadeOnDelete();
            $table->string('name');
            $table->char('currency_code', 3);
            $table->string('status', 32)->default('active');
            $table->timestampsTz();
            $table->unique(['tenant_id', 'id']);
            $table->unique(['tenant_id', 'name']);
            $table->index(['tenant_id', 'status']);
        });
        Schema::create('pricing_product_prices', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->foreignUuid('tenant_id')->constrained('tenants')->cascadeOnDelete();
            $table->uuid('price_book_id');
            $table->uuid('product_variant_id');
            $table->uuid('tax_category_id');
            $table->unsignedBigInteger('amount_minor');
            $table->timestampTz('effective_from');
            $table->timestampTz('effective_until')->nullable();
            $table->timestampsTz();
            $table->unique(['tenant_id', 'id']);
            $table->index(['tenant_id', 'price_book_id', 'product_variant_id', 'effective_from'], 'pricing_effective_lookup');
            $table->foreign(['tenant_id', 'price_book_id'])->references(['tenant_id', 'id'])->on('pricing_price_books')->restrictOnDelete();
            $table->foreign(['tenant_id', 'tax_category_id'])->references(['tenant_id', 'id'])->on('pricing_tax_categories')->restrictOnDelete();
            $table->foreign(['tenant_id', 'product_variant_id'])->references(['tenant_id', 'id'])->on('catalogue_product_variants')->restrictOnDelete();
        });
        if (DB::getDriverName() === 'pgsql') {
            foreach (['pricing_tax_categories', 'pricing_price_books', 'pricing_product_prices'] as $table) {
                DB::unprepared("ALTER TABLE {$table} ENABLE ROW LEVEL SECURITY; ALTER TABLE {$table} FORCE ROW LEVEL SECURITY; CREATE POLICY {$table}_tenant_isolation ON {$table} USING (tenant_id = nullif(current_setting('app.tenant_id', true), '')::uuid) WITH CHECK (tenant_id = nullif(current_setting('app.tenant_id', true), '')::uuid);");
            }
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('pricing_product_prices');
        Schema::dropIfExists('pricing_price_books');
        Schema::dropIfExists('pricing_tax_categories');
    }
};
