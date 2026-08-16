<?php

declare(strict_types=1);

namespace App\Modules\Onboarding\Application;

final readonly class NotificationDeliveryRequest
{
    public function __construct(
        public string $tenantId,
        public string $deliveryId,
        public string $notificationId,
        public string $recipientUserId,
        public string $recipientEmail,
        public string $channel,
        public string $title,
        public string $message,
    ) {}
}
