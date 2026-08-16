<?php
declare(strict_types=1);
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
return new class extends Migration {
 public function up(): void { Schema::table('users',function(Blueprint $table):void{$table->text('mfa_pending_secret')->nullable();$table->text('mfa_secret')->nullable();$table->timestampTz('mfa_confirmed_at')->nullable();}); }
 public function down(): void { Schema::table('users',fn(Blueprint $table)=>$table->dropColumn(['mfa_pending_secret','mfa_secret','mfa_confirmed_at'])); }
};
