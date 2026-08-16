<?php

declare(strict_types=1);

namespace Tests\Feature\Modules\Identity;

use App\Modules\Audit\Domain\TenantAuditEvent;
use App\Modules\Identity\Application\Contracts\TenantIamAdministration;
use App\Modules\Identity\Domain\MembershipStatus;
use App\Modules\Identity\Domain\Role;
use App\Modules\Identity\Domain\TenantMembership;
use App\Modules\Identity\Domain\User;
use App\Modules\Tenancy\Application\TenantScope;
use App\Modules\Tenancy\Domain\Tenant;
use App\Modules\Tenancy\Domain\TenantId;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use PHPUnit\Framework\Attributes\Test;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Tests\TestCase;

final class TenantIamAdministrationTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function an_authorized_actor_can_manage_roles_permissions_and_memberships_with_audit_evidence(): void
    {
        [$tenantId, $actor] = $this->adminFixture();
        $target = User::factory()->create();

        $result = $this->app->make(TenantScope::class)->run(
            TenantId::fromString($tenantId),
            function () use ($actor, $target): array {
                $admin = $this->app->make(TenantIamAdministration::class);
                $role = $admin->createRole($actor, 'Stock-Clerk', 'Inventory operator');
                $admin->replaceRolePermissions($actor, (string) $role->getKey(), ['catalogue.products.read']);
                $membership = $admin->assignMembership(
                    $actor,
                    (string) $target->getKey(),
                    (string) $role->getKey(),
                    MembershipStatus::Active,
                );

                return [$role, $membership, TenantAuditEvent::query()->orderBy('occurred_at')->get()];
            },
        );

        [$role, $membership, $events] = $result;
        self::assertSame('stock-clerk', $role->name);
        self::assertSame($role->getKey(), $membership->role_id);
        self::assertSame($tenantId, $membership->tenant_id);
        self::assertSame([
            'identity.role.created',
            'identity.role_permissions.replaced',
            'identity.membership.assigned',
        ], $events->pluck('event_type')->all());
        self::assertSame([$tenantId], $events->pluck('tenant_id')->unique()->values()->all());
    }

    #[Test]
    public function an_actor_without_the_management_permission_is_denied_without_evidence_or_mutation(): void
    {
        [$tenantId] = $this->adminFixture();
        $actor = User::factory()->create();
        TenantMembership::query()->create([
            'tenant_id' => $tenantId,
            'user_id' => $actor->getKey(),
            'status' => 'active',
        ]);

        try {
            $this->app->make(TenantScope::class)->run(
                TenantId::fromString($tenantId),
                fn () => $this->app->make(TenantIamAdministration::class)->createRole($actor, 'forbidden-role'),
            );
            self::fail('Unauthorized administration unexpectedly succeeded.');
        } catch (AccessDeniedHttpException) {
            self::assertSame(1, Role::query()->where('tenant_id', $tenantId)->count());
            self::assertSame(0, TenantAuditEvent::query()->count());
        }
    }

    #[Test]
    public function tenant_administration_evidence_is_append_only(): void
    {
        [$tenantId, $actor] = $this->adminFixture();
        $event = $this->app->make(TenantScope::class)->run(
            TenantId::fromString($tenantId),
            function () use ($actor): TenantAuditEvent {
                $this->app->make(TenantIamAdministration::class)->createRole($actor, 'audited-role');

                return TenantAuditEvent::query()->firstOrFail();
            },
        );

        $this->expectException(QueryException::class);
        $event->update(['event_type' => 'tampered']);
    }

    /** @return array{string, User} */
    private function adminFixture(): array
    {
        $tenant = Tenant::query()->create([
            'name' => 'NOVA Admin Tenant',
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

        return [(string) $tenant->getKey(), $actor];
    }
}
