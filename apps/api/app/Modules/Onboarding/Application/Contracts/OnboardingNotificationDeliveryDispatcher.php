<?php

declare(strict_types=1);

namespace App\Modules\Onboarding\Application\Contracts;

use App\Modules\Onboarding\Application\NotificationDeliveryResult;

interface OnboardingNotificationDeliveryDispatcher
{
    public function dispatch(string $userId, string $deliveryId): NotificationDeliveryResult;
}
