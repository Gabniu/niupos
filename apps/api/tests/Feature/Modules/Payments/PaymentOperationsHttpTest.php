<?php

declare(strict_types=1);

namespace Tests\Feature\Modules\Payments;

use App\Modules\Identity\Application\Contracts\ApiSessionManager;
use App\Modules\Identity\Domain\Role;
use App\Modules\Identity\Domain\TenantMembership;
use App\Modules\Identity\Domain\User;
use App\Modules\Payments\Application\Contracts\PaymentProcessor;
use App\Modules\Payments\Application\Data\PaymentResult;
use App\Modules\Tenancy\Domain\Tenant;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Route;
use Mockery\MockInterface;
use PHPUnit\Framework\Attributes\Test;
use RuntimeException;
use Tests\TestCase;

final class PaymentOperationsHttpTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function payment_operations_have_distinct_session_scoped_throttles(): void
    {
        $routes = collect(Route::getRoutes()->getRoutes());
        $initiate = $routes->first(fn ($route): bool => $route->uri() === 'api/v1/payments/attempts');
        $providerResult = $routes->first(fn ($route): bool => $route->uri() === 'api/v1/payments/attempts/{attempt}/provider-result');

        $this->assertNotNull($initiate);
        $this->assertNotNull($providerResult);
        $this->assertSame(
            ['api.session', 'tenant', 'permission:payments.create', 'throttle:payments-operations'],
            $initiate->gatherMiddleware(),
        );
        $this->assertSame(
            ['api.session', 'tenant', 'permission:payments.providerresults.manage', 'throttle:payments-provider-results'],
            $providerResult->gatherMiddleware(),
        );
    }

    #[Test]
    public function initiate_requires_authentication_tenant_and_permission_before_validation(): void
    {
        [$tenantId, , $token] = $this->identityFixture([]);

        $this->withHeader('X-Tenant-ID', $tenantId)->postJson('/api/v1/payments/attempts', [])->assertUnauthorized();
        $this->flushHeaders();
        $this->withToken($token)->postJson('/api/v1/payments/attempts', [])->assertBadRequest();
        $this->withToken($token)->withHeader('X-Tenant-ID', $tenantId)
            ->postJson('/api/v1/payments/attempts', [])->assertForbidden();
    }

    #[Test]
    public function initiate_maps_validated_contract_and_derives_actor_from_session(): void
    {
        [$tenantId, $actor, $token] = $this->identityFixture(['payments.create']);
        $saleId = fake()->uuid();
        $attemptId = fake()->uuid();
        $this->mock(PaymentProcessor::class, function (MockInterface $mock) use ($saleId, $actor, $attemptId): void {
            $mock->shouldReceive('initiate')->once()->with(
                $saleId, 'mpesa', 1250, 'KES', (string) $actor->getKey(), 'pay-001',
                ['customer_reference' => '254700000000'],
            )->andReturn(new PaymentResult($attemptId, $saleId, 'mpesa', 'pending', 1250, 'KES'));
        });

        $this->withToken($token)->withHeader('X-Tenant-ID', $tenantId)->withHeader('Idempotency-Key', 'pay-001')
            ->postJson('/api/v1/payments/attempts', [
                'sale_id' => $saleId, 'method' => 'mpesa', 'amount_minor' => 1250,
                'currency_code' => 'kes', 'actor_user_id' => fake()->uuid(),
                'provider_metadata' => ['customer_reference' => '254700000000'],
            ])->assertCreated()->assertJsonPath('data.attempt_id', $attemptId)->assertJsonMissingPath('data.actor_user_id');
    }

    #[Test]
    public function initiate_rejects_missing_key_and_non_allowlisted_provider_metadata(): void
    {
        [$tenantId, , $token] = $this->identityFixture(['payments.create']);
        $payload = ['sale_id' => fake()->uuid(), 'method' => 'mpesa', 'amount_minor' => 100, 'currency_code' => 'KES'];

        $this->withToken($token)->withHeader('X-Tenant-ID', $tenantId)->postJson('/api/v1/payments/attempts', $payload)
            ->assertUnprocessable()->assertJsonPath('error.code', 'PAYMENT_INVALID');
        $this->withToken($token)->withHeader('X-Tenant-ID', $tenantId)->withHeader('Idempotency-Key', 'x')
            ->postJson('/api/v1/payments/attempts', $payload + ['provider_metadata' => ['secret' => 'must-not-pass']])
            ->assertUnprocessable()->assertJsonValidationErrors(['provider_metadata']);
    }

    #[Test]
    public function provider_result_is_an_authenticated_privileged_operator_operation(): void
    {
        [$tenantId, , $ordinaryToken] = $this->identityFixture(['payments.create']);
        $attemptId = fake()->uuid();
        $payload = ['status' => 'succeeded', 'provider_reference' => 'MPESA-42', 'result_fingerprint' => str_repeat('a', 64)];

        $this->postJson("/api/v1/payments/attempts/{$attemptId}/provider-result", $payload)->assertUnauthorized();
        $this->withToken($ordinaryToken)->withHeader('X-Tenant-ID', $tenantId)
            ->postJson("/api/v1/payments/attempts/{$attemptId}/provider-result", $payload)->assertForbidden();

        [$privilegedTenant, , $privilegedToken] = $this->identityFixture(['payments.providerresults.manage']);
        $this->mock(PaymentProcessor::class, function (MockInterface $mock) use ($attemptId): void {
            $mock->shouldReceive('applyProviderResult')->once()->with($attemptId, 'succeeded', 'MPESA-42', str_repeat('a', 64))
                ->andReturn(new PaymentResult($attemptId, fake()->uuid(), 'mpesa', 'succeeded', 500, 'KES', 'MPESA-42'));
        });
        $this->withToken($privilegedToken)->withHeader('X-Tenant-ID', $privilegedTenant)
            ->postJson("/api/v1/payments/attempts/{$attemptId}/provider-result", $payload)
            ->assertOk()->assertJsonPath('data.status', 'succeeded');
    }

    #[Test]
    public function provider_result_validates_fingerprint_and_returns_generic_domain_failures(): void
    {
        [$tenantId, , $token] = $this->identityFixture(['payments.providerresults.manage']);
        $attemptId = fake()->uuid();
        $this->mock(PaymentProcessor::class, function (MockInterface $mock): void {
            $mock->shouldReceive('applyProviderResult')->once()->andThrow(new RuntimeException('sensitive provider detail'));
        });
        $this->withToken($token)->withHeader('X-Tenant-ID', $tenantId)
            ->postJson("/api/v1/payments/attempts/{$attemptId}/provider-result", [
                'status' => 'done', 'provider_reference' => '', 'result_fingerprint' => 'not-a-digest',
            ])->assertUnprocessable()->assertJsonValidationErrors(['status', 'provider_reference', 'result_fingerprint']);

        $response = $this->withToken($token)->withHeader('X-Tenant-ID', $tenantId)
            ->postJson("/api/v1/payments/attempts/{$attemptId}/provider-result", [
                'status' => 'failed', 'provider_reference' => 'REF', 'result_fingerprint' => str_repeat('b', 64),
            ])->assertConflict()->assertJsonPath('error.code', 'PAYMENT_CONFLICT');
        $this->assertStringNotContainsString('sensitive', $response->getContent());
    }

    /** @param list<string> $permissions @return array{string, User, string} */
    private function identityFixture(array $permissions): array
    {
        $tenant = Tenant::query()->create(['name' => 'Payment HTTP '.bin2hex(random_bytes(3)), 'jurisdiction_code' => 'KE', 'status' => 'active']);
        $actor = User::factory()->create();
        $role = Role::query()->create(['tenant_id' => $tenant->getKey(), 'name' => 'payments-'.bin2hex(random_bytes(3))]);
        foreach ($permissions as $permission) {
            DB::table('permissions')->insertOrIgnore(['id' => $permission, 'description' => $permission]);
            DB::table('role_permissions')->insert(['tenant_id' => $tenant->getKey(), 'role_id' => $role->getKey(), 'permission_id' => $permission]);
        }
        TenantMembership::query()->create([
            'tenant_id' => $tenant->getKey(), 'user_id' => $actor->getKey(), 'role_id' => $role->getKey(), 'status' => 'active',
        ]);

        return [(string) $tenant->getKey(), $actor, $this->app->make(ApiSessionManager::class)->issue($actor)->token];
    }
}
