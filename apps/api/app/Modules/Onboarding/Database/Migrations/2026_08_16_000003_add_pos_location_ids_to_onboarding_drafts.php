<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('onboarding_drafts', function (Blueprint $table): void {
            $table->uuid('company_id')->nullable();
            $table->uuid('branch_id')->nullable();
            $table->uuid('warehouse_id')->nullable();
            $table->uuid('register_id')->nullable();
            $table->string('location_completion_idempotency_key', 128)->nullable();
            $table->index(['tenant_id', 'register_id']);
            $table->foreign('company_id')->references('id')->on('companies')->nullOnDelete();
            $table->foreign('branch_id')->references('id')->on('branches')->nullOnDelete();
            $table->foreign('warehouse_id')->references('id')->on('warehouses')->nullOnDelete();
            $table->foreign('register_id')->references('id')->on('registers')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('onboarding_drafts', function (Blueprint $table): void {
            foreach (['company_id', 'branch_id', 'warehouse_id', 'register_id'] as $column) {
                $table->dropForeign([$column]);
            }
            $table->dropIndex('onboarding_drafts_tenant_id_register_id_index');
            $table->dropColumn(['company_id', 'branch_id', 'warehouse_id', 'register_id', 'location_completion_idempotency_key']);
        });
    }
};
