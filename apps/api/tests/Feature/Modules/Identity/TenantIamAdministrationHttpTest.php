<?php

declare(strict_types=1);

namespace Tests\Feature\Modules\Identity;

use App\Modules\Audit\Domain\TenantAuditEvent;
use App\Modules\Identity\Application\Contracts\ApiSessionManager;
use App\Modules\Identity\Domain\Role;
use App\Modules\Identity\Domain\TenantMembership;
use App\Modules\Identity\Domain\User;
use App\Modules\Tenancy\Domain\Tenant;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

final class TenantIamAdministrationHttpTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function an_authorized_session_can_administer_the_active_tenant_over_http(): void
    {
        [$tenantId, $actor, $token] = $this->adminFixture();
        $target = User::factory()->create();

        $roleResponse = $this->withToken($token)->withHeader('X-Tenant-ID', $tenantId)
            ->postJson('/api/v1/iam/roles', ['name' => 'Stock-Clerk', 'description' => 'Inventory operator'])
            ->assertCreated()
            ->assertJsonPath('data.name', 'stock-clerk');
        $roleId = $roleResponse->json('data.id');

        $this->withToken($token)->withHeader('X-Tenant-ID', $tenantId)
            ->putJson("/api/v1/iam/roles/{$roleId}/permissions", [
                'permissions' => ['catalogue.products.read'],
            ])->assertNoContent();

        $this->withToken($token)->withHeader('X-Tenant-ID', $tenantId)
            ->putJson('/api/v1/iam/memberships/'.$target->getKey(), [
                'role_id' => $roleId,
                'status' => 'active',
            ])->assertOk()
            ->assertJsonPath('data.user_id', $target->getKey())
            ->assertJsonPath('data.role_id', $roleId);

        self::assertSame(3, TenantAuditEvent::query()->where('tenant_id', $tenantId)->count());
        self::assertSame($actor->getKey(), TenantAuditEvent::query()->firstOrFail()->actor_user_id);
    }

    #[Test]
    public function authentication_tenant_admission_and_management_permission_each_fail_closed(): void
    {
        [$tenantId] = $this->adminFixture();
        $unprivileged = User::factory()->create();
        TenantMembership::query()->create([
            'tenant_id' => $tenantId,
            'user_id' => $unprivileged->getKey(),
            'status' => 'active',
        ]);
        $token = $this->app->make(ApiSessionManager::class)->issue($unprivileged)->token;

        $this->withHeader('X-Tenant-ID', $tenantId)
            ->postJson('/api/v1/iam/roles', ['name' => 'forbidden-role'])
            ->assertUnauthorized();
        $this->withToken($token)->withHeader('X-Tenant-ID', $tenantId)
            ->postJson('/api/v1/iam/roles', ['name' => 'forbidden-role'])
            ->assertForbidden();

        self::assertSame(1, Role::query()->where('tenant_id', $tenantId)->count());
        self::assertSame(0, TenantAuditEvent::query()->count());
    }

    #[Test]
    public function ownership_transfer_requires_a_current_session_mfa_elevation(): void
    {
        [$tenantId, $actor] = $this->adminFixture();
        TenantMembership::query()->where('tenant_id', $tenantId)->where('user_id', $actor->getKey())
            ->update(['is_owner' => true]);
        $target = User::factory()->create();
        TenantMembership::query()->create([
            'tenant_id' => $tenantId,
            'user_id' => $target->getKey(),
            'status' => 'active',
        ]);
        $issued = $this->app->make(ApiSessionManager::class)->issue($actor);
        $path = '/api/v1/iam/memberships/'.$target->getKey().'/ownership-transfer';

        $this->withToken($issued->token)->withHeader('X-Tenant-ID', $tenantId)
            ->postJson($path)->assertForbidden()->assertJsonPath('error.code', 'MFA_ELEVATION_REQUIRED');

        $this->app->make(ApiSessionManager::class)->elevate($issued->id, $actor);
        $this->withToken($issued->token)->withHeader('X-Tenant-ID', $tenantId)
            ->postJson($path)->assertNoContent();

        self::assertFalse((bool) TenantMembership::query()->where('tenant_id', $tenantId)
            ->where('user_id', $actor->getKey())->value('is_owner'));
        self::assertTrue((bool) TenantMembership::query()->where('tenant_id', $tenantId)
            ->where('user_id', $target->getKey())->value('is_owner'));
    }

    /** @return array{string, User, string} */
    private function adminFixture(): array
    {
        $tenant = Tenant::query()->create([
            'name' => 'NOVA HTTP Admin Tenant',
            'jurisdiction_code' => 'KE',
            'status' => 'active',
        ]);
        $actor = User::factory()->create();
        $role = Role::query()->create(['tenant_id' => $tenant->getKey(), 'name' => 'tenant-admin']);
        DB::table('permissions')->insert([
            ['id' => 'iam.roles.manage', 'description' => 'Manage tenant roles'],
            ['id' => 'iam.memberships.manage', 'description' => 'Manage tenant memberships'],
            ['id' => 'catalogue.products.read', 'description' => 'Read products'],
        ]);
        DB::table('role_permissions')->insert([
            ['tenant_id' => $tenant->getKey(), 'role_id' => $role->getKey(), 'permission_id' => 'iam.roles.manage'],
            ['tenant_id' => $tenant->getKey(), 'role_id' => $role->getKey(), 'permission_id' => 'iam.memberships.manage'],
        ]);
        TenantMembership::query()->create([
            'tenant_id' => $tenant->getKey(),
            'user_id' => $actor->getKey(),
            'role_id' => $role->getKey(),
            'status' => 'active',
        ]);
        $token = $this->app->make(ApiSessionManager::class)->issue($actor)->token;

        return [(string) $tenant->getKey(), $actor, $token];
    }
}
