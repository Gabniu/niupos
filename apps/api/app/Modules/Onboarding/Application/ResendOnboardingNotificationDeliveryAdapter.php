<?php

declare(strict_types=1);

namespace App\Modules\Onboarding\Application;

use App\Modules\Onboarding\Application\Contracts\OnboardingNotificationDeliveryAdapter;
use Illuminate\Support\Facades\Http;
use Throwable;

/**
 * Provider boundary for onboarding email delivery.
 *
 * This class is intentionally not bound by OnboardingServiceProvider yet. A
 * delivery worker must add idempotency, retries, recipient consent, and
 * persisted provider evidence before this adapter is allowed to send.
 */
final class ResendOnboardingNotificationDeliveryAdapter implements OnboardingNotificationDeliveryAdapter
{
    public function supports(string $channel): bool
    {
        return $channel === 'email';
    }

    public function deliver(NotificationDeliveryRequest $request): NotificationDeliveryResult
    {
        if (! $this->supports($request->channel)) {
            return new NotificationDeliveryResult('blocked', 'This delivery channel is not supported.', [
                'provider' => 'resend',
            ]);
        }

        $apiKey = trim((string) config('services.resend.key', ''));
        $from = trim((string) config('services.resend.from', ''));
        if (! (bool) config('services.resend.onboarding_enabled', false) || $apiKey === '' || $from === '') {
            return new NotificationDeliveryResult('blocked', 'Email delivery is not configured.', [
                'provider' => 'resend',
            ]);
        }

        if (filter_var($request->recipientEmail, FILTER_VALIDATE_EMAIL) === false) {
            return new NotificationDeliveryResult('blocked', 'The recipient email address is not valid.', [
                'provider' => 'resend',
            ]);
        }

        if ($request->title === '' || mb_strlen($request->title) > 160 || $request->message === '' || mb_strlen($request->message) > 10000) {
            return new NotificationDeliveryResult('blocked', 'The email content is outside the allowed limits.', [
                'provider' => 'resend',
            ]);
        }

        try {
            $response = Http::withToken($apiKey)
                ->withHeaders(['Idempotency-Key' => $request->deliveryId])
                ->acceptJson()
                ->asJson()
                ->timeout(10)
                ->post('https://api.resend.com/emails', [
                    'from' => $from,
                    'to' => [$request->recipientEmail],
                    'subject' => $request->title,
                    'text' => $request->message,
                ]);
        } catch (Throwable) {
            return new NotificationDeliveryResult('failed', 'The email provider could not be reached.', [
                'provider' => 'resend',
            ]);
        }

        if (! $response->successful()) {
            return new NotificationDeliveryResult('failed', 'The email provider rejected the message.', [
                'provider' => 'resend',
                'httpStatus' => $response->status(),
            ]);
        }

        $providerMessageId = $response->json('id');
        if (! is_string($providerMessageId) || $providerMessageId === '') {
            return new NotificationDeliveryResult('failed', 'The email provider returned no delivery reference.', [
                'provider' => 'resend',
            ]);
        }

        return new NotificationDeliveryResult('sent', 'Email accepted by the provider.', [
            'provider' => 'resend',
            'providerMessageId' => $providerMessageId,
        ]);
    }
}
