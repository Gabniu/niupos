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
            $table->uuid('tenant_id')->nullable()->after('user_id');
            $table->timestampTz('completed_at')->nullable();
            $table->string('completion_idempotency_key', 128)->nullable();
            $table->foreign('tenant_id')->references('id')->on('tenants')->nullOnDelete();
            $table->index(['user_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::table('onboarding_drafts', function (Blueprint $table): void {
            $table->dropForeign(['tenant_id']);
            $table->dropIndex('onboarding_drafts_user_id_status_index');
            $table->dropColumn(['tenant_id', 'completed_at', 'completion_idempotency_key']);
        });
    }
};
