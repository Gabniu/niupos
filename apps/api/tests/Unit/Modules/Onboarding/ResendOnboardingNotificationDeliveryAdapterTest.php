<?php

declare(strict_types=1);

namespace Tests\Unit\Modules\Onboarding;

use App\Modules\Onboarding\Application\NotificationDeliveryRequest;
use App\Modules\Onboarding\Application\ResendOnboardingNotificationDeliveryAdapter;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

final class ResendOnboardingNotificationDeliveryAdapterTest extends TestCase
{
    #[Test]
    public function it_sends_only_bounded_email_payloads_and_returns_provider_evidence(): void
    {
        config([
            'services.resend.key' => 'test-resend-key',
            'services.resend.from' => 'NIU <no-reply@example.test>',
            'services.resend.onboarding_enabled' => true,
        ]);
        Http::fake([
            'https://api.resend.com/emails' => Http::response(['id' => 'msg_test_123'], 200),
        ]);

        $result = (new ResendOnboardingNotificationDeliveryAdapter)->deliver(new NotificationDeliveryRequest(
            'tenant-1',
            'delivery-1',
            'notification-1',
            'user-1',
            'owner@example.test',
            'email',
            'Setup complete',
            'Your setup is ready.',
        ));

        self::assertSame('sent', $result->status);
        self::assertSame('msg_test_123', $result->evidence['providerMessageId']);
        Http::assertSent(static function (Request $request): bool {
            return $request->hasHeader('Authorization', 'Bearer test-resend-key')
                && $request->hasHeader('Idempotency-Key', 'delivery-1')
                && $request['from'] === 'NIU <no-reply@example.test>'
                && $request['to'] === ['owner@example.test']
                && $request['subject'] === 'Setup complete'
                && $request['text'] === 'Your setup is ready.';
        });
    }

    #[Test]
    public function it_fails_closed_when_resend_is_not_configured(): void
    {
        Http::fake();
        config(['services.resend.key' => '', 'services.resend.from' => '']);

        $result = (new ResendOnboardingNotificationDeliveryAdapter)->deliver(new NotificationDeliveryRequest(
            'tenant-1',
            'delivery-1',
            'notification-1',
            'user-1',
            'owner@example.test',
            'email',
            'Setup complete',
            'Your setup is ready.',
        ));

        self::assertSame('blocked', $result->status);
        Http::assertNothingSent();
    }
}
