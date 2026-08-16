<?php

declare(strict_types=1);

namespace Tests\Feature\Modules\Channels;

use App\Modules\Channels\Application\ChannelRegistrationView;
use App\Modules\Channels\Application\Contracts\ChannelRegistrationManager;
use App\Modules\Channels\Domain\ChannelRegistration;
use App\Modules\Tenancy\Application\TenantScope;
use App\Modules\Tenancy\Domain\Tenant;
use App\Modules\Tenancy\Domain\TenantId;
use DomainException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

final class ChannelRegistrationManagerTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function it_creates_a_tenant_scoped_customer_channel_without_exposing_secrets(): void
    {
        $tenant = Tenant::query()->create(['name' => 'Web Shop', 'jurisdiction_code' => 'KE', 'status' => 'active']);

        $registration = $this->inTenant((string) $tenant->getKey(), function (ChannelRegistrationManager $manager): ChannelRegistrationView {
            return $manager->create('web', 'Web Shop storefront', 'production', ['locale' => 'en-KE'], ['https://shop.example.test/callback'], 'web-1');
        });

        self::assertSame('web', $registration->channel);
        self::assertSame('customer', $registration->audience);
        self::assertSame('draft', $registration->status);
        self::assertNotSame('', $registration->clientId);
        self::assertFalse($registration->toArray()['secretAvailable']);
        self::assertSame(1, ChannelRegistration::query()->count());
    }

    #[Test]
    public function it_requires_approval_before_a_registration_can_progress(): void
    {
        $tenant = Tenant::query()->create(['name' => 'Mobile Shop', 'jurisdiction_code' => 'KE', 'status' => 'active']);
        $result = $this->inTenant((string) $tenant->getKey(), function (ChannelRegistrationManager $manager): ChannelRegistrationView {
            $draft = $manager->create('mobile', 'Mobile Shop app', 'staging', [], [], 'mobile-1');

            return $manager->requestApproval($draft->id);
        });

        self::assertSame('approval_required', $result->status);
    }

    #[Test]
    public function it_rejects_secret_material(): void
    {
        $tenant = Tenant::query()->create(['name' => 'Protected Shop', 'jurisdiction_code' => 'KE', 'status' => 'active']);

        $this->expectException(DomainException::class);
        $this->inTenant((string) $tenant->getKey(), function (ChannelRegistrationManager $manager): void {
            $manager->create('web', 'Protected storefront', 'production', ['clientSecret' => 'must-not-be-accepted'], [], 'secret-1');
        });
    }

    #[Test]
    public function it_rejects_duplicate_channel_environments(): void
    {
        $tenant = Tenant::query()->create(['name' => 'Duplicate Shop', 'jurisdiction_code' => 'KE', 'status' => 'active']);
        $this->inTenant((string) $tenant->getKey(), function (ChannelRegistrationManager $manager): void {
            $manager->create('web', 'First storefront', 'production', [], [], 'duplicate-1');
        });

        $this->expectException(DomainException::class);
        $this->inTenant((string) $tenant->getKey(), function (ChannelRegistrationManager $manager): void {
            $manager->create('web', 'Second storefront', 'production', [], [], 'duplicate-2');
        });
    }

    #[Test]
    public function the_same_idempotency_key_replays_the_same_registration(): void
    {
        $tenant = Tenant::query()->create(['name' => 'Replay Shop', 'jurisdiction_code' => 'KE', 'status' => 'active']);
        $result = $this->inTenant((string) $tenant->getKey(), function (ChannelRegistrationManager $manager): array {
            $first = $manager->create('web', 'Replay storefront', 'development', [], [], 'replay-1');
            $replay = $manager->create('web', 'Replay storefront', 'development', [], [], 'replay-1');

            return [$first, $replay];
        });

        self::assertSame($result[0]->id, $result[1]->id);
        self::assertSame(1, ChannelRegistration::query()->count());
    }

    #[Test]
    public function registrations_are_isolated_by_tenant_context(): void
    {
        $first = Tenant::query()->create(['name' => 'First Channel Shop', 'jurisdiction_code' => 'KE', 'status' => 'active']);
        $second = Tenant::query()->create(['name' => 'Second Channel Shop', 'jurisdiction_code' => 'KE', 'status' => 'active']);

        $this->inTenant((string) $first->getKey(), function (ChannelRegistrationManager $manager): void {
            $manager->create('web', 'First storefront', 'development', [], [], 'first-1');
        });
        $this->inTenant((string) $second->getKey(), function (ChannelRegistrationManager $manager): void {
            $manager->create('mobile', 'Second app', 'development', [], [], 'second-1');
        });

        self::assertCount(1, $this->inTenant((string) $first->getKey(), fn (ChannelRegistrationManager $manager) => $manager->registrations()));
        self::assertCount(1, $this->inTenant((string) $second->getKey(), fn (ChannelRegistrationManager $manager) => $manager->registrations()));
        self::assertSame('web', $this->inTenant((string) $first->getKey(), fn (ChannelRegistrationManager $manager) => $manager->registrations()->first())->channel);
    }

    private function inTenant(string $tenantId, callable $callback): mixed
    {
        return $this->app->make(TenantScope::class)->run(TenantId::fromString($tenantId), fn () => $callback($this->app->make(ChannelRegistrationManager::class)));
    }
}
