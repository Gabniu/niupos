<?php

declare(strict_types=1);

namespace App\Modules\Onboarding\Application\Contracts;

use App\Modules\Onboarding\Domain\OnboardingSetupEvent;

interface OnboardingSetupNotificationWriter
{
    public function fromEvent(OnboardingSetupEvent $event): void;
}
