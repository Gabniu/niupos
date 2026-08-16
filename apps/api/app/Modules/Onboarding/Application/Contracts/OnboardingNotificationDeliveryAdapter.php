<?php

declare(strict_types=1);

namespace App\Modules\Onboarding\Application\Contracts;

use App\Modules\Onboarding\Application\NotificationDeliveryRequest;
use App\Modules\Onboarding\Application\NotificationDeliveryResult;

interface OnboardingNotificationDeliveryAdapter
{
    public function supports(string $channel): bool;

    public function deliver(NotificationDeliveryRequest $request): NotificationDeliveryResult;
}
