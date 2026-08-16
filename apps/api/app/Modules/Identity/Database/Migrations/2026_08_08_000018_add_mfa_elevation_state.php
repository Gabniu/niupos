<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table): void {
            $table->unsignedBigInteger('mfa_last_accepted_step')->nullable();
        });

        Schema::table('api_sessions', function (Blueprint $table): void {
            $table->timestampTz('mfa_elevated_until')->nullable()->index();
        });
    }

    public function down(): void
    {
        Schema::table('api_sessions', function (Blueprint $table): void {
            $table->dropColumn('mfa_elevated_until');
        });

        Schema::table('users', function (Blueprint $table): void {
            $table->dropColumn('mfa_last_accepted_step');
        });
    }
};
