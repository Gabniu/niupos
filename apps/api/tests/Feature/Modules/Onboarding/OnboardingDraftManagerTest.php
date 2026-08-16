<?php

declare(strict_types=1);

namespace Tests\Feature\Modules\Onboarding;

use App\Modules\Identity\Domain\User;
use App\Modules\Channels\Application\Contracts\ChannelRegistrationManager;
use App\Modules\Onboarding\Application\Contracts\OnboardingDraftManager;
use App\Modules\Identity\Domain\TenantMembership;
use App\Modules\Tenancy\Domain\Tenant;
use App\Modules\Tenancy\Application\TenantScope;
use App\Modules\Tenancy\Domain\TenantId;
use DomainException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

final class OnboardingDraftManagerTest extends TestCase
{
    use RefreshDatabase;

    public function test_channel_selection_returns_a_branch_plan_without_placeholder_records(): void
    {
        $user = User::factory()->create();
        $manager = $this->app->make(OnboardingDraftManager::class);

        $draft = $manager->save((string) $user->getKey(), [
            'channelSelection' => 'web_mobile',
        ], 0, 'draft-1');

        self::assertSame('web_mobile', $draft->channelSelection?->value);
        self::assertSame('industry', $draft->nextStep);
        self::assertSame(1, $draft->revision);
        self::assertContains('Prepare a web storefront client and publication checklist', $draft->automated);
        self::assertContains('Prepare a mobile client and build configuration', $draft->automated);
        self::assertSame([], $draft->answers);
    }

    public function test_replaying_the_same_idempotency_key_does_not_duplicate_a_save(): void
    {
        $user = User::factory()->create();
        $manager = $this->app->make(OnboardingDraftManager::class);

        $first = $manager->save((string) $user->getKey(), ['channelSelection' => 'pos'], 0, 'same-key');
        $replay = $manager->save((string) $user->getKey(), ['channelSelection' => 'web'], 0, 'same-key');

        self::assertSame($first->id, $replay->id);
        self::assertSame(1, $replay->revision);
        self::assertSame('pos', $replay->channelSelection?->value);
    }

    public function test_stale_revision_is_rejected_and_drafts_are_user_scoped(): void
    {
        $userA = User::factory()->create();
        $userB = User::factory()->create();
        $manager = $this->app->make(OnboardingDraftManager::class);

        $manager->save((string) $userA->getKey(), ['channelSelection' => 'pos'], 0, 'a-1');
        $this->expectException(DomainException::class);
        $manager->save((string) $userA->getKey(), ['channelSelection' => 'mobile'], 0, 'a-2');

        self::assertNull($manager->find((string) $userB->getKey()));
    }

    public function test_next_step_changes_after_common_answers(): void
    {
        $user = User::factory()->create();
        $manager = $this->app->make(OnboardingDraftManager::class);
        $userId = (string) $user->getKey();

        $draft = $manager->save($userId, ['channelSelection' => 'pos', 'industryProfile' => 'grocery'], 0, 'step-1');
        self::assertSame('organization', $draft->nextStep);

        $draft = $manager->save($userId, ['answers' => ['organizationName' => 'Real Shop']], 1, 'step-2');
        self::assertSame('pos_locations', $draft->nextStep);
    }

    public function test_pos_completion_creates_one_real_tenant_and_owner_membership(): void
    {
        $user = User::factory()->create();
        $manager = $this->app->make(OnboardingDraftManager::class);
        $userId = (string) $user->getKey();

        $manager->save($userId, [
            'channelSelection' => 'pos',
            'industryProfile' => 'grocery',
            'answers' => ['organizationName' => 'Kaniu Grocery'],
        ], 0, 'draft-1');

        $completed = $manager->completePos($userId, 1, 'complete-1');
        $replay = $manager->completePos($userId, 1, 'complete-2');

        self::assertSame('completed', $completed->status);
        self::assertNotNull($completed->tenantId);
        self::assertSame($completed->tenantId, $replay->tenantId);
        self::assertSame(1, Tenant::query()->count());
        self::assertSame(1, TenantMembership::query()->where('user_id', $userId)->where('is_owner', true)->count());
    }

    public function test_non_pos_channel_creates_an_organization_without_pos_records(): void
    {
        $user = User::factory()->create();
        $manager = $this->app->make(OnboardingDraftManager::class);
        $userId = (string) $user->getKey();
        $manager->save($userId, ['channelSelection' => 'web', 'industryProfile' => 'bakery', 'answers' => ['organizationName' => 'Bakery']], 0, 'web-1');

        $completed = $manager->completeOrganization($userId, 1, 'complete-web');

        self::assertSame('completed', $completed->status);
        self::assertNotNull($completed->tenantId);
        self::assertNull($completed->registerId);
        self::assertSame('web_storefront', $completed->nextStep);
    }

    public function test_web_and_mobile_channel_progress_is_server_derived_from_real_registrations(): void
    {
        $user = User::factory()->create();
        $manager = $this->app->make(OnboardingDraftManager::class);
        $userId = (string) $user->getKey();
        $manager->save($userId, ['channelSelection' => 'web_mobile', 'industryProfile' => 'grocery', 'answers' => ['organizationName' => 'Omni Shop']], 0, 'channels-1');
        $completed = $manager->completeOrganization($userId, 1, 'channels-complete');

        self::assertSame('web_storefront', $completed->nextStep);
        $tenantId = (string) $completed->tenantId;
        $this->app->make(TenantScope::class)->run(TenantId::fromString($tenantId), function (): void {
            $this->app->make(ChannelRegistrationManager::class)->create('web', 'Omni Shop web', 'production', [], [], 'omni-web-1');
        });

        self::assertSame('mobile_app', $manager->find($userId)?->nextStep);
        $this->app->make(TenantScope::class)->run(TenantId::fromString($tenantId), function (): void {
            $this->app->make(ChannelRegistrationManager::class)->create('mobile', 'Omni Shop mobile', 'production', [], [], 'omni-mobile-1');
        });

        self::assertSame('ready', $manager->find($userId)?->nextStep);
    }

    public function test_pos_location_completion_creates_real_company_branch_warehouse_and_register_once(): void
    {
        $user = User::factory()->create();
        $manager = $this->app->make(OnboardingDraftManager::class);
        $userId = (string) $user->getKey();
        $manager->save($userId, [
            'channelSelection' => 'pos',
            'industryProfile' => 'bakery',
            'answers' => ['organizationName' => 'Kaniu Cakes'],
        ], 0, 'draft-location-1');
        $manager->completePos($userId, 1, 'complete-location-1');

        $setup = [
            'companyName' => 'Kaniu Cakes',
            'branchCode' => 'MAIN',
            'branchName' => 'Main shop',
            'warehouseCode' => 'MAIN-WH',
            'warehouseName' => 'Main stockroom',
            'registerCode' => 'REG-01',
            'registerName' => 'Front counter',
        ];
        $ready = $manager->completePosLocations($userId, $setup, 1, 'locations-1');
        $replay = $manager->completePosLocations($userId, $setup, 1, 'locations-2');

        self::assertSame('ready', $ready->status);
        self::assertNotNull($ready->companyId);
        self::assertNotNull($ready->branchId);
        self::assertNotNull($ready->warehouseId);
        self::assertNotNull($ready->registerId);
        self::assertSame($ready->registerId, $replay->registerId);
    }
}
