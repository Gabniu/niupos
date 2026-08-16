<?php

declare(strict_types=1);

namespace Tests\Feature\Modules\Identity;

use App\Modules\Identity\Application\Contracts\FederatedIdentityMapper;
use App\Modules\Identity\Application\Contracts\FederatedIdentityResolver;
use App\Modules\Identity\Application\Contracts\OidcDiscoveryClient;
use App\Modules\Identity\Application\Contracts\OidcIdentityVerifier;
use App\Modules\Identity\Application\Contracts\OidcTokenClient;
use App\Modules\Identity\Application\FederatedIdentity;
use App\Modules\Identity\Application\OidcProviderMetadata;
use App\Modules\Identity\Application\OidcTokenResponse;
use App\Modules\Identity\Domain\TenantMembership;
use App\Modules\Identity\Domain\User;
use App\Modules\Tenancy\Domain\Tenant;
use DateTimeImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use RuntimeException;
use Tests\TestCase;

final class FederatedIdentityResolverTest extends TestCase
{
    use RefreshDatabase;

    public function test_federation_is_fail_closed_until_a_verified_oidc_adapter_is_enabled(): void
    {
        self::assertNull($this->app->make(FederatedIdentityResolver::class)->resolve('eyJ.invalid.token'));
    }

    public function test_discovery_uses_the_provider_path_and_requires_s256_pkce(): void
    {
        config()->set('identity.federation.issuer', 'https://novaauth.niuautomations.com/api/auth');
        Http::fake([
            'https://novaauth.niuautomations.com/.well-known/openid-configuration/api/auth' => Http::response([
                'issuer' => 'https://novaauth.niuautomations.com/api/auth',
                'authorization_endpoint' => 'https://novaauth.niuautomations.com/api/auth/oauth2/authorize',
                'token_endpoint' => 'https://novaauth.niuautomations.com/api/auth/oauth2/token',
                'jwks_uri' => 'https://novaauth.niuautomations.com/api/auth/jwks',
                'userinfo_endpoint' => 'https://novaauth.niuautomations.com/api/auth/oauth2/userinfo',
                'code_challenge_methods_supported' => ['S256'],
            ]),
        ]);

        $metadata = $this->app->make(OidcDiscoveryClient::class)->metadata();

        self::assertSame('https://novaauth.niuautomations.com/api/auth', $metadata->issuer);
        self::assertSame(['S256'], $metadata->codeChallengeMethods);
        Http::assertSent(fn ($request): bool => $request->url() === 'https://novaauth.niuautomations.com/.well-known/openid-configuration/api/auth');
    }

    public function test_federation_start_is_hidden_until_explicitly_enabled(): void
    {
        $this->getJson('/api/v1/auth/federation/start')
            ->assertNotFound()
            ->assertJsonPath('error.code', 'AUTH_FEDERATION_UNAVAILABLE');
    }

    public function test_enabled_federation_start_persists_a_short_lived_pkce_transaction(): void
    {
        config()->set('identity.federation.enabled', true);
        config()->set('identity.federation.client_id', 'pos-local');
        config()->set('identity.federation.redirect_uri', 'https://pos.example.test/auth/callback');
        $this->app->instance(OidcDiscoveryClient::class, new class implements OidcDiscoveryClient
        {
            public function metadata(): OidcProviderMetadata
            {
                return new OidcProviderMetadata(
                    'https://novaauth.niuautomations.com/api/auth',
                    'https://novaauth.niuautomations.com/api/auth/oauth2/authorize',
                    'https://novaauth.niuautomations.com/api/auth/oauth2/token',
                    'https://novaauth.niuautomations.com/api/auth/jwks',
                    'https://novaauth.niuautomations.com/api/auth/oauth2/userinfo',
                    ['S256'],
                );
            }
        });
        $response = $this->getJson('/api/v1/auth/federation/start')->assertOk();
        $url = (string) $response->json('data.authorization_url');
        parse_str((string) parse_url($url, PHP_URL_QUERY), $query);
        self::assertSame('S256', $query['code_challenge_method']);
        self::assertSame('pos-local', $query['client_id']);
        self::assertNotSame('', $query['state']);
        self::assertNotSame('', $query['nonce']);
        self::assertNotSame('', $query['code_challenge']);
        self::assertArrayNotHasKey('prompt', $query);
        $transaction = Cache::get('nova.identity.oidc.state.'.$query['state']);
        self::assertIsArray($transaction);
        self::assertArrayHasKey('nonce', $transaction);
        self::assertArrayHasKey('verifier', $transaction);
        self::assertArrayHasKey('redirect_uri', $transaction);
    }

    public function test_callback_is_generic_and_fail_closed_when_federation_is_disabled(): void
    {
        $this->getJson('/api/v1/auth/federation/callback?state=invalid-state&code=one-time-code')
            ->assertNotFound()
            ->assertJsonPath('error.code', 'AUTH_FEDERATION_UNAVAILABLE');
    }

    public function test_callback_consumes_state_once_and_fails_closed_when_token_exchange_fails(): void
    {
        config()->set('identity.federation.enabled', true);
        $this->app->instance(OidcTokenClient::class, new class implements OidcTokenClient
        {
            public function exchange(string $code, string $verifier, string $redirectUri): OidcTokenResponse
            {
                throw new RuntimeException('test token exchange failure');
            }
        });
        Cache::put('nova.identity.oidc.state.'.str_repeat('a', 32), [
            'nonce' => str_repeat('b', 32),
            'verifier' => str_repeat('c', 64),
            'redirect_uri' => 'https://pos.example.test/auth/callback',
        ], now()->addMinutes(10));

        $this->getJson('/api/v1/auth/federation/callback?state='.str_repeat('a', 32).'&code=one-time-code')
            ->assertNotFound()
            ->assertJsonPath('error.code', 'AUTH_FEDERATION_UNAVAILABLE');
        self::assertNull(Cache::get('nova.identity.oidc.state.'.str_repeat('a', 32)));
    }

    public function test_token_transport_requires_bearer_id_token_and_uses_pkce_verifier(): void
    {
        config()->set('identity.federation.client_id', 'pos-local');
        $this->app->instance(OidcDiscoveryClient::class, new class implements OidcDiscoveryClient
        {
            public function metadata(): OidcProviderMetadata
            {
                return new OidcProviderMetadata(
                    'https://novaauth.niuautomations.com/api/auth',
                    'https://novaauth.niuautomations.com/api/auth/oauth2/authorize',
                    'https://novaauth.niuautomations.com/api/auth/oauth2/token',
                    'https://novaauth.niuautomations.com/api/auth/jwks',
                    'https://novaauth.niuautomations.com/api/auth/oauth2/userinfo',
                    ['S256'],
                );
            }
        });
        Http::fake([
            'https://novaauth.niuautomations.com/api/auth/oauth2/token' => Http::response([
                'access_token' => 'opaque-access-token', 'token_type' => 'Bearer', 'expires_in' => 3600,
                'id_token' => 'signed-id-token', 'scope' => 'openid profile email',
            ]),
        ]);

        $token = $this->app->make(OidcTokenClient::class)->exchange('one-time-code', 'pkce-verifier', 'https://pos.example.test/auth/callback');

        self::assertSame('Bearer', $token->tokenType);
        self::assertSame(3600, $token->expiresIn);
        Http::assertSent(fn ($request): bool => $request->data()['code_verifier'] === 'pkce-verifier');
    }

    public function test_identity_verifier_rejects_malformed_tokens_without_networking(): void
    {
        $this->app->instance(OidcDiscoveryClient::class, new class implements OidcDiscoveryClient
        {
            public function metadata(): OidcProviderMetadata
            {
                return new OidcProviderMetadata('https://novaauth.niuautomations.com/api/auth', '', '', 'https://novaauth.niuautomations.com/api/auth/jwks', '', ['S256']);
            }
        });
        Http::fake();

        $this->expectException(RuntimeException::class);
        $this->app->make(OidcIdentityVerifier::class)->verify(
            new OidcTokenResponse('access', 'Bearer', 3600, 'not-a-jwt', null),
            'nonce',
        );
        Http::assertNothingSent();
    }

    public function test_mapping_requires_exact_issuer_subject_and_active_tenant_membership(): void
    {
        $tenant = Tenant::query()->create(['name' => 'Federated Tenant', 'jurisdiction_code' => 'KE', 'status' => 'active']);
        $user = User::factory()->create([
            'identity_issuer' => 'https://novaauth.niuautomations.com/api/auth',
            'identity_subject' => 'subject-123',
        ]);
        $membership = TenantMembership::query()->create([
            'tenant_id' => $tenant->getKey(),
            'user_id' => $user->getKey(),
            'status' => 'active',
        ]);

        $admission = $this->app->make(FederatedIdentityMapper::class)->admit(
            new FederatedIdentity(
                'https://novaauth.niuautomations.com/api/auth',
                'subject-123',
                $user->email,
                $user->name,
                new DateTimeImmutable('+5 minutes'),
            ),
            (string) $tenant->getKey(),
        );

        self::assertNotNull($admission);
        self::assertSame($user->getKey(), $admission->user->getKey());
        self::assertSame($membership->getKey(), $admission->membership->getKey());
    }

    public function test_mapping_does_not_fall_back_to_email_or_inactive_memberships(): void
    {
        $tenant = Tenant::query()->create(['name' => 'Federated Tenant', 'jurisdiction_code' => 'KE', 'status' => 'active']);
        $user = User::factory()->create([
            'email' => 'linked@example.test',
            'identity_issuer' => 'https://novaauth.niuautomations.com/api/auth',
            'identity_subject' => 'subject-123',
        ]);
        TenantMembership::query()->create([
            'tenant_id' => $tenant->getKey(),
            'user_id' => $user->getKey(),
            'status' => 'revoked',
        ]);

        $mapper = $this->app->make(FederatedIdentityMapper::class);
        $sameEmailDifferentSubject = new FederatedIdentity(
            'https://novaauth.niuautomations.com/api/auth',
            'other-subject',
            'linked@example.test',
            $user->name,
            new DateTimeImmutable('+5 minutes'),
        );

        self::assertNull($mapper->admit($sameEmailDifferentSubject, (string) $tenant->getKey()));
        self::assertNull($mapper->admit(new FederatedIdentity(
            'https://novaauth.niuautomations.com/other',
            'subject-123',
            $user->email,
            $user->name,
            new DateTimeImmutable('+5 minutes'),
        ), (string) $tenant->getKey()));
    }

    public function test_mapping_rejects_inactive_tenants_and_invalid_tenant_ids(): void
    {
        $tenant = Tenant::query()->create(['name' => 'Suspended Tenant', 'jurisdiction_code' => 'KE', 'status' => 'suspended']);
        $user = User::factory()->create([
            'identity_issuer' => 'https://novaauth.niuautomations.com/api/auth',
            'identity_subject' => 'subject-123',
        ]);
        TenantMembership::query()->create(['tenant_id' => $tenant->getKey(), 'user_id' => $user->getKey(), 'status' => 'active']);
        $identity = new FederatedIdentity('https://novaauth.niuautomations.com/api/auth', 'subject-123', null, null, new DateTimeImmutable('+5 minutes'));

        $mapper = $this->app->make(FederatedIdentityMapper::class);
        self::assertNull($mapper->admit($identity, (string) $tenant->getKey()));
        self::assertNull($mapper->admit($identity, 'not-a-uuid'));
    }
}
