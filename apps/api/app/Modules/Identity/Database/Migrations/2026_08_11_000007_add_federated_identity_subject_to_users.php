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
            $table->string('identity_issuer', 2048)->nullable();
            $table->string('identity_subject', 512)->nullable();
            $table->unique(['identity_issuer', 'identity_subject'], 'users_identity_subject_unique');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table): void {
            $table->dropUnique('users_identity_subject_unique');
            $table->dropColumn(['identity_issuer', 'identity_subject']);
        });
    }
};
