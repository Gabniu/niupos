<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('onboarding_drafts', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->uuid('user_id');
            $table->string('channel_selection', 16)->nullable();
            $table->string('industry_profile', 64)->nullable();
            $table->json('answers')->default('{}');
            $table->string('current_step', 64)->default('channel');
            $table->unsignedInteger('revision')->default(0);
            $table->string('status', 32)->default('in_progress');
            $table->string('last_idempotency_key', 128)->nullable();
            $table->timestampsTz();
            $table->foreign('user_id')->references('id')->on('users')->cascadeOnDelete();
            $table->unique('user_id');
            $table->index(['status', 'updated_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('onboarding_drafts');
    }
};
