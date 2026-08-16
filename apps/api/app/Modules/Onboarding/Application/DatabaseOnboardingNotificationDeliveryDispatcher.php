<?php

declare(strict_types=1);

namespace App\Modules\Onboarding\Application;

use App\Modules\Onboarding\Application\Contracts\OnboardingNotificationDeliveryAdapter;
use App\Modules\Onboarding\Application\Contracts\OnboardingNotificationDeliveryDispatcher;
use App\Modules\Tenancy\Application\TenantContext;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Throwable;

final readonly class DatabaseOnboardingNotificationDeliveryDispatcher implements OnboardingNotificationDeliveryDispatcher
{
    public function __construct(private TenantContext $context, private OnboardingNotificationDeliveryAdapter $adapter) {}

    public function dispatch(string $userId, string $deliveryId): NotificationDeliveryResult
    {
        $tenantId = (string) $this->context->id();
        $prepared = DB::transaction(function () use ($tenantId, $userId, $deliveryId): array|NotificationDeliveryResult {
            $delivery = DB::table('onboarding_notification_deliveries as deliveries')
                ->join('onboarding_setup_notifications as notifications', function ($join): void {
                    $join->on('notifications.id', '=', 'deliveries.notification_id')
                        ->on('notifications.tenant_id', '=', 'deliveries.tenant_id');
                })
                ->join('users', 'users.id', '=', 'deliveries.recipient_user_id')
                ->where('deliveries.tenant_id', $tenantId)
                ->where('deliveries.id', $deliveryId)
                ->where('deliveries.recipient_user_id', $userId)
                ->lockForUpdate()
                ->first([
                    'deliveries.id', 'deliveries.channel', 'deliveries.status',
                    'deliveries.attempts', 'notifications.id as notification_id',
                    'deliveries.last_attempted_at',
                    'notifications.title', 'notifications.message',
                    'users.id as recipient_user_id', 'users.email',
                    'users.email_verified_at',
                ]);

            if ($delivery === null) {
                return new NotificationDeliveryResult('blocked', 'The delivery was not found.', []);
            }
            if ((string) $delivery->status === 'sent') {
                return new NotificationDeliveryResult('sent', 'The message was already accepted by the provider.', []);
            }
            if ((string) $delivery->status === 'sending') {
                $lastAttempt = $delivery->last_attempted_at === null
                    ? null
                    : CarbonImmutable::parse((string) $delivery->last_attempted_at);
                if ($lastAttempt === null || $lastAttempt->greaterThan(now()->subMinutes(15))) {
                    return new NotificationDeliveryResult('blocked', 'This message is already being sent.', []);
                }
            }
            if ((string) $delivery->channel !== 'email') {
                return new NotificationDeliveryResult('blocked', 'This delivery channel is not available yet.', []);
            }

            $preferences = DB::table('onboarding_notification_preferences')
                ->where('tenant_id', $tenantId)
                ->first(['email_enabled']);
            if ($preferences === null || ! (bool) $preferences->email_enabled) {
                return new NotificationDeliveryResult('blocked', 'Email updates are turned off.', []);
            }
            if (! is_string($delivery->email) || filter_var($delivery->email, FILTER_VALIDATE_EMAIL) === false) {
                return new NotificationDeliveryResult('blocked', 'A valid recipient email is not available.', []);
            }
            if ($delivery->email_verified_at === null) {
                return new NotificationDeliveryResult('blocked', 'The recipient email is not verified.', []);
            }

            $maxAttempts = max(1, (int) config('onboarding.delivery.max_attempts', 3));
            if ((int) $delivery->attempts >= $maxAttempts) {
                return new NotificationDeliveryResult('blocked', 'The message reached its retry limit.', []);
            }

            DB::table('onboarding_notification_deliveries')
                ->where('tenant_id', $tenantId)
                ->where('id', $deliveryId)
                ->update([
                    'status' => 'sending',
                    'attempts' => (int) $delivery->attempts + 1,
                    'last_attempted_at' => now(),
                    'blocked_reason' => null,
                    'updated_at' => now(),
                ]);

            return [
                'deliveryId' => (string) $delivery->id,
                'notificationId' => (string) $delivery->notification_id,
                'recipientUserId' => (string) $delivery->recipient_user_id,
                'recipientEmail' => (string) $delivery->email,
                'channel' => (string) $delivery->channel,
                'title' => (string) $delivery->title,
                'message' => (string) $delivery->message,
            ];
        });

        if ($prepared instanceof NotificationDeliveryResult) {
            return $prepared;
        }

        try {
            $result = $this->adapter->deliver(new NotificationDeliveryRequest(
                $tenantId,
                $prepared['deliveryId'],
                $prepared['notificationId'],
                $prepared['recipientUserId'],
                $prepared['recipientEmail'],
                $prepared['channel'],
                $prepared['title'],
                $prepared['message'],
            ));
        } catch (Throwable) {
            $result = new NotificationDeliveryResult('failed', 'The delivery could not be completed.', []);
        }

        DB::table('onboarding_notification_deliveries')
            ->where('tenant_id', $tenantId)
            ->where('id', $deliveryId)
            ->update([
                'status' => $result->status,
                'blocked_reason' => $result->status === 'sent' ? null : $result->message,
                'provider_message_id' => is_string($result->evidence['providerMessageId'] ?? null) ? $result->evidence['providerMessageId'] : null,
                'sent_at' => $result->status === 'sent' ? now() : null,
                'updated_at' => now(),
            ]);

        return $result;
    }
}
