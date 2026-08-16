<?php

declare(strict_types=1);

namespace Tests\Feature\Modules\Identity;

use App\Modules\Identity\Domain\TenantMembership;
use App\Modules\Identity\Domain\User;
use App\Modules\Identity\Infrastructure\DatabaseTenantAccessAuthorizer;
use App\Modules\Tenancy\Domain\Tenant;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use PHPUnit\Framework\Attributes\Test;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Tests\TestCase;

final class DatabaseTenantAccessAuthorizerTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function it_authorizes_only_an_active_membership_for_the_authenticated_user(): void
    {
        $tenant = Tenant::create([
            'name' => 'NOVA Test Shop',
            'jurisdiction_code' => 'KE',
            'status' => 'active',
        ]);
        $user = User::factory()->create();
        TenantMembership::create([
            'tenant_id' => $tenant->id,
            'user_id' => $user->id,
            'status' => 'active',
        ]);
        $request = Request::create('/api/example');
        $request->setUserResolver(fn (): User => $user);

        $this->app->make(DatabaseTenantAccessAuthorizer::class)->assertCanAccess(
            $request,
            $tenant->id,
        );

        self::addToAssertionCount(1);
    }

    #[Test]
    public function it_denies_an_authenticated_user_without_membership(): void
    {
        $tenant = Tenant::create([
            'name' => 'NOVA Test Shop',
            'jurisdiction_code' => 'KE',
            'status' => 'active',
        ]);
        $user = User::factory()->create();
        $request = Request::create('/api/example');
        $request->setUserResolver(fn (): User => $user);

        $this->expectException(AccessDeniedHttpException::class);

        $this->app->make(DatabaseTenantAccessAuthorizer::class)->assertCanAccess(
            $request,
            $tenant->id,
        );
    }

    #[Test]
    public function it_denies_an_inactive_membership(): void
    {
        $tenant = Tenant::create([
            'name' => 'NOVA Test Shop',
            'jurisdiction_code' => 'KE',
            'status' => 'active',
        ]);
        $user = User::factory()->create();
        TenantMembership::create([
            'tenant_id' => $tenant->id,
            'user_id' => $user->id,
            'status' => 'revoked',
        ]);
        $request = Request::create('/api/example');
        $request->setUserResolver(fn (): User => $user);

        $this->expectException(AccessDeniedHttpException::class);

        $this->app->make(DatabaseTenantAccessAuthorizer::class)->assertCanAccess(
            $request,
            $tenant->id,
        );
    }
}
