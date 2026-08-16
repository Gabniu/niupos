<?php

declare(strict_types=1);

namespace Tests\Feature\Modules\Identity;

use App\Modules\Identity\Application\Contracts\PermissionAuthorizer;
use App\Modules\Identity\Domain\PermissionKey;
use App\Modules\Identity\Domain\Role;
use App\Modules\Identity\Domain\TenantMembership;
use App\Modules\Identity\Domain\User;
use App\Modules\Tenancy\Application\TenantScope;
use App\Modules\Tenancy\Domain\Tenant;
use App\Modules\Tenancy\Domain\TenantId;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

final class DatabasePermissionAuthorizerTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function it_allows_only_an_assigned_permission_inside_the_active_tenant(): void
    {
        [$tenantId, $user, $role] = $this->identityFixture();
        DB::table('permissions')->insert([
            ['id' => 'catalogue.products.read', 'description' => 'Read products'],
            ['id' => 'catalogue.products.write', 'description' => 'Write products'],
        ]);
        DB::table('role_permissions')->insert([
            'tenant_id' => $tenantId,
            'role_id' => $role->getKey(),
            'permission_id' => 'catalogue.products.read',
        ]);

        $scope = $this->app->make(TenantScope::class);
        $authorizer = $this->app->make(PermissionAuthorizer::class);

        $result = $scope->run(TenantId::fromString($tenantId), fn (): array => [
            $authorizer->allows($user, new PermissionKey('catalogue.products.read')),
            $authorizer->allows($user, new PermissionKey('catalogue.products.write')),
        ]);

        self::assertSame([true, false], $result);
    }

    #[Test]
    public function it_denies_without_an_active_tenant_scope(): void
    {
        [, $user] = $this->identityFixture();

        self::assertFalse($this->app->make(PermissionAuthorizer::class)
            ->allows($user, new PermissionKey('catalogue.products.read')));
    }

    #[Test]
    public function it_does_not_inherit_a_permission_from_another_tenant(): void
    {
        [$tenantId, $user] = $this->identityFixture();
        $otherTenant = Tenant::query()->create([
            'name' => 'Other Tenant',
            'jurisdiction_code' => 'KE',
            'status' => 'active',
        ]);
        $otherRole = Role::query()->create([
            'tenant_id' => $otherTenant->getKey(),
            'name' => 'owner',
        ]);
        DB::table('permissions')->insert([
            'id' => 'catalogue.products.write',
            'description' => 'Write products',
        ]);
        DB::table('role_permissions')->insert([
            'tenant_id' => $otherTenant->getKey(),
            'role_id' => $otherRole->getKey(),
            'permission_id' => 'catalogue.products.write',
        ]);
        TenantMembership::query()->create([
            'tenant_id' => $otherTenant->getKey(),
            'user_id' => $user->getKey(),
            'role_id' => $otherRole->getKey(),
            'status' => 'active',
        ]);

        $allowed = $this->app->make(TenantScope::class)->run(
            TenantId::fromString($tenantId),
            fn (): bool => $this->app->make(PermissionAuthorizer::class)
                ->allows($user, new PermissionKey('catalogue.products.write')),
        );

        self::assertFalse($allowed);
    }

    /** @return array{string, User, Role} */
    private function identityFixture(): array
    {
        $tenant = Tenant::query()->create([
            'name' => 'NOVA Test Tenant',
            'jurisdiction_code' => 'KE',
            'status' => 'active',
        ]);
        $user = User::factory()->create();
        $role = Role::query()->create([
            'tenant_id' => $tenant->getKey(),
            'name' => 'cashier',
            'description' => 'Checkout operator',
        ]);
        TenantMembership::query()->create([
            'tenant_id' => $tenant->getKey(),
            'user_id' => $user->getKey(),
            'role_id' => $role->getKey(),
            'status' => 'active',
        ]);

        return [(string) $tenant->getKey(), $user, $role];
    }
}
