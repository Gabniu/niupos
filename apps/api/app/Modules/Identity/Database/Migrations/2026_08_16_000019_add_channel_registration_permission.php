<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::table('permissions')->updateOrInsert(
            ['id' => 'channels.registrations.manage'],
            ['description' => 'Manage customer channel registrations', 'created_at' => now(), 'updated_at' => now()],
        );
        DB::table('permissions')->updateOrInsert(
            ['id' => 'onboarding.provision'],
            ['description' => 'Preview and approve onboarding provisioning', 'created_at' => now(), 'updated_at' => now()],
        );

        DB::table('roles')->where('name', 'tenant-owner')->get(['id', 'tenant_id'])->each(function (object $role): void {
            DB::table('role_permissions')->updateOrInsert(
                ['tenant_id' => $role->tenant_id, 'role_id' => $role->id, 'permission_id' => 'channels.registrations.manage'],
            );
            DB::table('role_permissions')->updateOrInsert(
                ['tenant_id' => $role->tenant_id, 'role_id' => $role->id, 'permission_id' => 'onboarding.provision'],
            );
        });
    }

    public function down(): void
    {
        DB::table('role_permissions')->where('permission_id', 'channels.registrations.manage')->delete();
        DB::table('role_permissions')->where('permission_id', 'onboarding.provision')->delete();
        DB::table('permissions')->where('id', 'channels.registrations.manage')->delete();
        DB::table('permissions')->where('id', 'onboarding.provision')->delete();
    }
};
