<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('tenant_workspace_preferences', function (Blueprint $table): void {
            $table->string('reporting_timezone', 64)->default('UTC')->after('kiosk_mode');
        });
    }

    public function down(): void
    {
        Schema::table('tenant_workspace_preferences', function (Blueprint $table): void {
            $table->dropColumn('reporting_timezone');
        });
    }
};
