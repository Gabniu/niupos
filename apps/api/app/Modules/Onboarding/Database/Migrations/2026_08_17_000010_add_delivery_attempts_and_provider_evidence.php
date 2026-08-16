<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('onboarding_notification_deliveries', function (Blueprint $table): void {
            $table->unsignedSmallInteger('attempts')->default(0)->after('status');
            $table->timestampTz('last_attempted_at')->nullable()->after('attempts');
            $table->string('provider_message_id', 255)->nullable()->after('sent_at');
        });
    }

    public function down(): void
    {
        Schema::table('onboarding_notification_deliveries', function (Blueprint $table): void {
            $table->dropColumn(['attempts', 'last_attempted_at', 'provider_message_id']);
        });
    }
};
