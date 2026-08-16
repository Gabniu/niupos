<?php

declare(strict_types=1);

namespace Tests\Feature\Modules\Onboarding;

use App\Modules\Identity\Domain\User;
use App\Modules\Onboarding\Application\Contracts\OnboardingDraftManager;
use App\Modules\Onboarding\Application\Contracts\OnboardingProvisioningManager;
use App\Modules\Onboarding\Application\Contracts\OnboardingProvisioningWorker;
use App\Modules\Onboarding\Application\Contracts\OnboardingSetupNotificationReader;
use App\Modules\Onboarding\Application\Contracts\OnboardingSetupTimelineReader;
use App\Modules\Onboarding\Application\Contracts\OnboardingNotificationDeliveryAdapter;
use App\Modules\Onboarding\Application\NotificationDeliveryRequest;
use App\Modules\Onboarding\Application\NotificationDeliveryResult;
use App\Modules\Onboarding\Application\Contracts\OnboardingNotificationDeliveryDispatcher;
use App\Modules\Tenancy\Application\TenantScope;
use App\Modules\Tenancy\Domain\TenantId;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

final class OnboardingProvisioningManagerTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function it_persists_a_dry_run_plan_and_stops_external_actions_at_approval(): void
    {
        [$userId, $tenantId] = $this->completedDraft('web', 'grocery');

        $run = $this->inTenant($tenantId, fn () => $this->app->make(OnboardingProvisioningManager::class)->preview($userId, 'preview-1'));

        self::assertTrue($run->dryRun);
        self::assertSame('needs_action', $run->status);
        self::assertTrue($run->approvalRequired);
        self::assertContains('web.storefront.publication', array_column($run->actions, 'code'));
        self::assertStringNotContainsString('secret', json_encode($run->toArray()));
        self::assertSame('provisioning.previewed', $this->inTenant($tenantId, fn () => $this->app->make(OnboardingSetupTimelineReader::class)->events()->first()['type']));
        self::assertDatabaseHas('onboarding_setup_notifications', ['tenant_id' => $tenantId, 'recipient_user_id' => $userId, 'type' => 'provisioning.previewed', 'read_at' => null]);
    }

    #[Test]
    public function preview_is_idempotent_and_approval_is_a_separate_transition(): void
    {
        [$userId, $tenantId] = $this->completedDraft('mobile', 'bakery');

        $result = $this->inTenant($tenantId, function () use ($userId): array {
            $manager = $this->app->make(OnboardingProvisioningManager::class);
            $first = $manager->preview($userId, 'preview-replay');
            $replay = $manager->preview($userId, 'preview-replay');
            $approved = $manager->approve($userId, $first->id, 'owner-approval-1');

            return [$first, $replay, $approved];
        });

        self::assertSame($result[0]->id, $result[1]->id);
        self::assertSame('needs_action', $result[1]->status);
        self::assertSame('queued', $result[2]->status);
        self::assertTrue(collect($result[2]->actions)->every(static fn (array $action): bool => $action['status'] === 'queued'));
    }

    #[Test]
    public function pos_only_plan_has_no_external_publication_action(): void
    {
        [$userId, $tenantId] = $this->completedDraft('pos', 'supermarket');

        $run = $this->inTenant($tenantId, fn () => $this->app->make(OnboardingProvisioningManager::class)->preview($userId, 'pos-preview'));

        self::assertSame('queued', $run->status);
        self::assertFalse($run->approvalRequired);
        self::assertNotContains('web.storefront.publication', array_column($run->actions, 'code'));
        self::assertNotContains('mobile.build.release', array_column($run->actions, 'code'));
    }

    #[Test]
    public function worker_fails_closed_when_no_verified_executor_exists(): void
    {
        [$userId, $tenantId] = $this->completedDraft('web', 'grocery');
        $run = $this->inTenant($tenantId, fn () => $this->app->make(OnboardingProvisioningManager::class)->preview($userId, 'worker-preview'));
        $approved = $this->inTenant($tenantId, fn () => $this->app->make(OnboardingProvisioningManager::class)->approve($userId, $run->id, 'worker-approval'));
        $blocked = $this->inTenant($tenantId, fn () => $this->app->make(OnboardingProvisioningWorker::class)->process($userId, $approved->id));

        self::assertSame('needs_action', $blocked->status);
        self::assertContains('succeeded', array_column($blocked->actions, 'status'));
        self::assertContains('needs_action', array_column($blocked->actions, 'status'));
        self::assertDatabaseHas('tenant_workspace_preferences', ['tenant_id' => $tenantId, 'side_panel_visible' => true, 'kiosk_mode' => false]);
        self::assertDatabaseHas('onboarding_notification_preferences', ['tenant_id' => $tenantId, 'in_app_enabled' => true, 'email_enabled' => false, 'sms_enabled' => false, 'push_enabled' => false]);
    }

    #[Test]
    public function worker_completes_a_pos_only_run_with_verified_internal_executors(): void
    {
        [$userId, $tenantId] = $this->completedDraft('pos', 'supermarket');
        $run = $this->inTenant($tenantId, fn () => $this->app->make(OnboardingProvisioningManager::class)->preview($userId, 'pos-worker-preview'));
        $completed = $this->inTenant($tenantId, fn () => $this->app->make(OnboardingProvisioningWorker::class)->process($userId, $run->id));

        self::assertSame('completed', $completed->status);
        self::assertNotNull($completed->completedAt);
        self::assertTrue(collect($completed->actions)->every(static fn (array $action): bool => $action['status'] === 'succeeded'));
        self::assertSame('provisioning.completed', $this->inTenant($tenantId, fn () => $this->app->make(OnboardingSetupTimelineReader::class)->events()->first()['type']));
    }

    #[Test]
    public function setup_notifications_are_tenant_scoped_and_can_be_marked_read(): void
    {
        [$userId, $tenantId] = $this->completedDraft('pos', 'bakery');
        $this->inTenant($tenantId, fn () => $this->app->make(OnboardingProvisioningManager::class)->preview($userId, 'notification-preview'));

        $reader = $this->app->make(OnboardingSetupNotificationReader::class);
        $notification = $this->inTenant($tenantId, fn () => $reader->notifications($userId)->first());
        self::assertIsArray($notification);
        self::assertNull($notification['readAt']);

        $read = $this->inTenant($tenantId, fn () => $reader->markRead($userId, (string) $notification['id']));
        self::assertNotNull($read['readAt']);
    }

    #[Test]
    public function enabled_external_channels_create_blocked_delivery_intents_without_sending(): void
    {
        [$userId, $tenantId] = $this->completedDraft('pos', 'bakery');
        $this->inTenant($tenantId, function (): void {
            DB::table('onboarding_notification_preferences')->insert([
                'tenant_id' => (string) app(\App\Modules\Tenancy\Application\TenantContext::class)->id(),
                'in_app_enabled' => true,
                'email_enabled' => true,
                'sms_enabled' => false,
                'push_enabled' => false,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        });
        $this->inTenant($tenantId, fn () => $this->app->make(OnboardingProvisioningManager::class)->preview($userId, 'delivery-preview'));

        self::assertDatabaseHas('onboarding_notification_deliveries', [
            'tenant_id' => $tenantId,
            'channel' => 'email',
            'status' => 'blocked',
            'blocked_reason' => 'No verified external delivery adapter is registered.',
        ]);
        self::assertDatabaseCount('onboarding_notification_deliveries', 1);
    }

    #[Test]
    public function explicit_email_delivery_is_idempotent_and_persists_provider_evidence(): void
    {
        [$userId, $tenantId] = $this->completedDraft('pos', 'bakery');
        $this->inTenant($tenantId, function (): void {
            DB::table('onboarding_notification_preferences')->insert([
                'tenant_id' => (string) app(\App\Modules\Tenancy\Application\TenantContext::class)->id(),
                'in_app_enabled' => true,
                'email_enabled' => true,
                'sms_enabled' => false,
                'push_enabled' => false,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        });
        $this->inTenant($tenantId, fn () => $this->app->make(OnboardingProvisioningManager::class)->preview($userId, 'delivery-send-preview'));
        $deliveryId = (string) $this->inTenant($tenantId, fn () => DB::table('onboarding_notification_deliveries')->value('id'));
        $adapter = new class implements OnboardingNotificationDeliveryAdapter
        {
            public int $calls = 0;

            public function supports(string $channel): bool
            {
                return $channel === 'email';
            }

            public function deliver(NotificationDeliveryRequest $request): NotificationDeliveryResult
            {
                $this->calls++;

                return new NotificationDeliveryResult('sent', 'accepted', ['providerMessageId' => 'msg-test-1']);
            }
        };
        $this->app->instance(OnboardingNotificationDeliveryAdapter::class, $adapter);

        $first = $this->inTenant($tenantId, fn () => $this->app->make(OnboardingNotificationDeliveryDispatcher::class)->dispatch($userId, $deliveryId));
        $second = $this->inTenant($tenantId, fn () => $this->app->make(OnboardingNotificationDeliveryDispatcher::class)->dispatch($userId, $deliveryId));

        self::assertSame('sent', $first->status);
        self::assertSame('sent', $second->status);
        self::assertSame(1, $adapter->calls);
        self::assertDatabaseHas('onboarding_notification_deliveries', [
            'id' => $deliveryId,
            'tenant_id' => $tenantId,
            'status' => 'sent',
            'provider_message_id' => 'msg-test-1',
            'attempts' => 1,
        ]);
    }

    /** @return array{string, string} */
    private function completedDraft(string $channel, string $industry): array
    {
        $user = User::factory()->create();
        $drafts = $this->app->make(OnboardingDraftManager::class);
        $userId = (string) $user->getKey();
        $drafts->save($userId, ['channelSelection' => $channel, 'industryProfile' => $industry, 'answers' => ['organizationName' => 'Provisioning Test']], 0, 'draft-'.bin2hex(random_bytes(3)));
        $completed = $drafts->completeOrganization($userId, 1, 'complete-'.bin2hex(random_bytes(3)));

        return [$userId, (string) $completed->tenantId];
    }

    private function inTenant(string $tenantId, callable $callback): mixed
    {
        return $this->app->make(TenantScope::class)->run(TenantId::fromString($tenantId), $callback);
    }
}
