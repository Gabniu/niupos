<?php

declare(strict_types=1);

namespace App\Modules\Onboarding\Application;

use App\Modules\Onboarding\Application\Contracts\OnboardingSetupNotificationWriter;
use App\Modules\Onboarding\Domain\OnboardingSetupEvent;
use App\Modules\Onboarding\Domain\OnboardingSetupNotification;
use App\Modules\Onboarding\Domain\OnboardingSetupNotificationDelivery;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

final readonly class DatabaseOnboardingSetupNotificationWriter implements OnboardingSetupNotificationWriter
{
    public function fromEvent(OnboardingSetupEvent $event): void
    {
        if ($event->actor_user_id === null) {
            return;
        }

        $notification = OnboardingSetupNotification::query()->firstOrCreate(
            [
                'tenant_id' => $event->tenant_id,
                'event_id' => $event->getKey(),
                'recipient_user_id' => $event->actor_user_id,
            ],
            [
                'run_id' => $event->run_id,
                'type' => (string) $event->type,
                'title' => $this->title((string) $event->type),
                'message' => (string) $event->message,
                'read_at' => null,
            ],
        );

        $preferences = DB::table('onboarding_notification_preferences')
            ->where('tenant_id', (string) $event->tenant_id)
            ->first(['email_enabled', 'sms_enabled', 'push_enabled']);
        foreach (['email' => 'email_enabled', 'sms' => 'sms_enabled', 'push' => 'push_enabled'] as $channel => $column) {
            if ($preferences === null || ! (bool) $preferences->{$column}) {
                continue;
            }

            OnboardingSetupNotificationDelivery::query()->firstOrCreate(
                [
                    'tenant_id' => $event->tenant_id,
                    'notification_id' => $notification->getKey(),
                    'channel' => $channel,
                ],
                [
                    'recipient_user_id' => $event->actor_user_id,
                    'status' => 'blocked',
                    'blocked_reason' => 'No verified external delivery adapter is registered.',
                ],
            );
        }
    }

    private function title(string $type): string
    {
        return match (Str::afterLast($type, '.')) {
            'previewed' => 'Setup plan ready',
            'approved' => 'Setup plan approved',
            'completed' => 'Setup completed',
            'blocked' => 'Setup needs attention',
            default => 'Setup update',
        };
    }
}
