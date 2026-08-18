<?php

declare(strict_types=1);

namespace Tests\Feature\Modules\Sync;

use App\Modules\Identity\Application\Contracts\ApiSessionManager;
use App\Modules\Identity\Domain\Role;
use App\Modules\Identity\Domain\TenantMembership;
use App\Modules\Identity\Domain\User;
use App\Modules\Register\Application\Contracts\RegisterDeviceManager;
use App\Modules\Sync\Application\Contracts\SyncBootstrap;
use App\Modules\Sync\Application\Contracts\SyncProtocol;
use App\Modules\Sync\Application\Data\SyncChange;
use App\Modules\Sync\Application\Data\SyncChangePage;
use App\Modules\Tenancy\Application\TenantScope;
use App\Modules\Tenancy\Domain\Branch;
use App\Modules\Tenancy\Domain\Company;
use App\Modules\Tenancy\Domain\Tenant;
use App\Modules\Tenancy\Domain\TenantId;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Route;
use Mockery\MockInterface;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

final class SyncHttpTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function sync_routes_have_ordered_authentication_permission_and_distinct_throttles(): void
    {
        $routes = collect(Route::getRoutes()->getRoutes());
        $pull = $routes->first(fn ($route): bool => $route->uri() === 'api/v1/sync/changes');
        $commands = $routes->first(fn ($route): bool => $route->uri() === 'api/v1/sync/commands');
        $bootstrap = $routes->first(fn ($route): bool => $route->uri() === 'api/v1/sync/bootstrap');

        self::assertSame(['api.session', 'tenant', 'permission:sync.use', 'throttle:sync-pull'], $pull?->gatherMiddleware());
        self::assertSame(['api.session', 'tenant', 'permission:sync.use', 'throttle:sync-commands'], $commands?->gatherMiddleware());
        self::assertSame(['api.session', 'tenant', 'permission:sync.use', 'throttle:sync-bootstrap'], $bootstrap?->gatherMiddleware());
    }

    #[Test]
    public function bootstrap_returns_catalogue_pricing_and_cursor_snapshot(): void
    {
        [$tenant, , $token] = $this->identityFixture(['sync.use']);
        $device = fake()->uuid();
        $this->mock(SyncBootstrap::class, function (MockInterface $mock) use ($device): void {
            $mock->shouldReceive('snapshot')->once()->with(strtolower($device), null)->andReturn([
                'version' => '1', 'cursor' => 12, 'generatedAt' => '2026-08-08T10:00:00+03:00',
                'catalogue' => ['categories' => [], 'unitsOfMeasure' => [], 'products' => [], 'variants' => [], 'barcodes' => []],
                'pricing' => ['taxCategories' => [], 'priceBooks' => [], 'prices' => []],
            ]);
        });

        $this->withToken($token)->withHeaders(['X-Tenant-ID' => $tenant, 'X-Device-Id' => $device])
            ->getJson('/api/v1/sync/bootstrap')->assertOk()->assertJsonPath('cursor', 12)->assertJsonPath('catalogue.products', []);
    }

    #[Test]
    public function bootstrap_page_forwards_bounded_cursor_parameters_and_preserves_page_metadata(): void
    {
        [$tenant, , $token] = $this->identityFixture(['sync.use']);
        $device = fake()->uuid();
        $this->mock(SyncBootstrap::class, function (MockInterface $mock) use ($device): void {
            $mock->shouldReceive('snapshot')->once()->with(strtolower($device), [
                'section' => 'catalogue', 'collection' => 'products', 'after_id' => '01989f8e-1111-7111-8111-111111111111',
                'limit' => 2, 'snapshot_cursor' => 12,
            ])->andReturn([
                'version' => '1', 'cursor' => 12, 'generatedAt' => '2026-08-08T10:00:00+03:00',
                'catalogue' => ['categories' => [], 'unitsOfMeasure' => [], 'products' => [['id' => '01989f8e-2222-7222-8222-222222222222']], 'variants' => [], 'barcodes' => []],
                'pricing' => ['taxCategories' => [], 'priceBooks' => [], 'prices' => []],
                'page' => ['section' => 'catalogue', 'collection' => 'products', 'afterId' => '01989f8e-1111-7111-8111-111111111111', 'nextAfterId' => null, 'hasMore' => false, 'limit' => 2],
            ]);
        });

        $this->withToken($token)->withHeaders(['X-Tenant-ID' => $tenant, 'X-Device-Id' => $device])
            ->getJson('/api/v1/sync/bootstrap?section=catalogue&collection=products&after_id=01989f8e-1111-7111-8111-111111111111&limit=2&snapshot_cursor=12')
            ->assertOk()->assertJsonPath('page.hasMore', false)->assertJsonPath('page.limit', 2);
    }

    #[Test]
    public function bootstrap_rejects_unbounded_or_unknown_page_parameters(): void
    {
        [$tenant, , $token] = $this->identityFixture(['sync.use']);
        $device = fake()->uuid();
        $headers = ['X-Tenant-ID' => $tenant, 'X-Device-Id' => $device];
        $this->withToken($token)->withHeaders($headers)->getJson('/api/v1/sync/bootstrap?section=catalogue&collection=products&limit=501')
            ->assertUnprocessable()->assertJsonValidationErrors(['limit']);
        $this->withToken($token)->withHeaders($headers)->getJson('/api/v1/sync/bootstrap?unknown=value')
            ->assertUnprocessable()->assertJsonPath('error.code', 'SYNC_INVALID');
    }

    #[Test]
    public function middleware_rejects_missing_session_tenant_and_permission_before_transport_validation(): void
    {
        [$tenant, , $token] = $this->identityFixture([]);
        $this->withHeader('X-Tenant-ID', $tenant)->getJson('/api/v1/sync/changes')->assertUnauthorized();
        $this->flushHeaders();
        $this->withToken($token)->getJson('/api/v1/sync/changes')->assertBadRequest();
        $this->withToken($token)->withHeader('X-Tenant-ID', $tenant)->getJson('/api/v1/sync/changes')->assertForbidden();
    }

    #[Test]
    public function pull_maps_the_exact_v1_shape_and_bounded_device_header(): void
    {
        [$tenant, , $token] = $this->identityFixture(['sync.use']);
        $device = fake()->uuid();
        $this->mock(SyncProtocol::class, function (MockInterface $mock) use ($device): void {
            $mock->shouldReceive('pull')->once()->with(strtolower($device), 7, 25)->andReturn(new SyncChangePage('1', 9, [
                new SyncChange(9, 'catalogue.variant', 'variant-1', 'upsert', ['name' => 'Tea'], '2026-08-08T10:00:00+03:00'),
            ], false));
        });

        $this->withToken($token)->withHeaders(['X-Tenant-ID' => $tenant, 'X-Device-Id' => $device])
            ->getJson('/api/v1/sync/changes?after_cursor=7&limit=25')->assertOk()->assertExactJson([
                'version' => '1', 'cursor' => 9, 'changes' => [[
                    'cursor' => 9, 'entityType' => 'catalogue.variant', 'entityId' => 'variant-1', 'operation' => 'upsert',
                    'payload' => ['name' => 'Tea'], 'occurredAt' => '2026-08-08T10:00:00+03:00',
                ]], 'hasMore' => false,
            ]);

        $this->flushHeaders();
        $this->withToken($token)->withHeader('X-Tenant-ID', $tenant)->getJson('/api/v1/sync/changes')
            ->assertUnprocessable()->assertJsonPath('error.code', 'SYNC_DEVICE_INVALID');
        $this->flushHeaders();
        $this->withToken($token)->withHeaders(['X-Tenant-ID' => $tenant, 'X-Device-Id' => $device])
            ->getJson('/api/v1/sync/changes?limit=501')->assertUnprocessable()->assertJsonValidationErrors(['limit']);
    }

    #[Test]
    public function command_intake_is_strict_and_replay_returns_the_stable_rejected_receipt(): void
    {
        [$tenant, , $token] = $this->identityFixture(['sync.use']);
        $device = $this->activeDevice($tenant);
        $command = [
            'version' => '1', 'commandId' => fake()->uuid(), 'type' => 'sales.finalize.v1',
            'occurredAt' => '2026-08-08T10:00:00+03:00', 'payload' => ['saleId' => fake()->uuid()],
        ];
        $headers = ['X-Tenant-ID' => $tenant, 'X-Device-Id' => $device];
        $first = $this->withToken($token)->withHeaders($headers)->postJson('/api/v1/sync/commands', $command)
            ->assertOk()->assertJsonPath('data.status', 'rejected')->assertJsonPath('data.resultCode', 'invalid_sales_command');
        $second = $this->withToken($token)->withHeaders($headers)->postJson('/api/v1/sync/commands', $command)->assertOk();
        self::assertSame($first->json(), $second->json());
        self::assertSame(1, DB::table('sync_command_inbox')->where('command_id', $command['commandId'])->count());

        $this->withToken($token)->withHeaders($headers)->postJson('/api/v1/sync/commands', $command + ['tenantId' => $tenant])
            ->assertUnprocessable()->assertJsonPath('error.code', 'SYNC_INVALID');
    }

    #[Test]
    public function inactive_or_cross_tenant_device_has_one_generic_response(): void
    {
        [$tenantA, , $tokenA] = $this->identityFixture(['sync.use']);
        [$tenantB] = $this->identityFixture(['sync.use']);
        $deviceB = $this->activeDevice($tenantB);
        $response = $this->withToken($tokenA)->withHeaders(['X-Tenant-ID' => $tenantA, 'X-Device-Id' => $deviceB])
            ->getJson('/api/v1/sync/changes')->assertNotFound()->assertJsonPath('error.code', 'SYNC_DEVICE_UNAVAILABLE');
        self::assertStringNotContainsString($deviceB, $response->getContent());
    }

    /** @param list<string> $permissions @return array{string, User, string} */
    private function identityFixture(array $permissions): array
    {
        $tenant = Tenant::query()->create(['name' => 'Sync HTTP '.bin2hex(random_bytes(3)), 'jurisdiction_code' => 'KE', 'status' => 'active']);
        $actor = User::factory()->create();
        $role = Role::query()->create(['tenant_id' => $tenant->getKey(), 'name' => 'sync-'.bin2hex(random_bytes(3))]);
        foreach ($permissions as $permission) {
            DB::table('permissions')->insertOrIgnore(['id' => $permission, 'description' => $permission]);
            DB::table('role_permissions')->insert(['tenant_id' => $tenant->getKey(), 'role_id' => $role->getKey(), 'permission_id' => $permission]);
        }
        TenantMembership::query()->create(['tenant_id' => $tenant->getKey(), 'user_id' => $actor->getKey(), 'role_id' => $role->getKey(), 'status' => 'active']);

        return [(string) $tenant->getKey(), $actor, $this->app->make(ApiSessionManager::class)->issue($actor)->token];
    }

    private function activeDevice(string $tenantId): string
    {
        $company = Company::query()->create(['tenant_id' => $tenantId, 'name' => 'Sync Company', 'status' => 'active']);
        $branch = Branch::query()->create(['tenant_id' => $tenantId, 'company_id' => $company->getKey(), 'code' => 'SYNC', 'name' => 'Sync Branch', 'status' => 'active']);

        return $this->app->make(TenantScope::class)->run(TenantId::fromString($tenantId), function () use ($branch): string {
            $manager = $this->app->make(RegisterDeviceManager::class);
            $register = $manager->createRegister((string) $branch->getKey(), 'SYNC-POS', 'Sync POS');
            $issued = $manager->issueDeviceEnrollment((string) $register->getKey(), 'Sync Device', now()->addHour());

            return (string) $manager->consumeDeviceEnrollment($issued->token)->public_id;
        });
    }
}
