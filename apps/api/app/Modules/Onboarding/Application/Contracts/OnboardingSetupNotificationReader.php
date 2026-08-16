<?php

declare(strict_types=1);

namespace App\Modules\Onboarding\Application\Contracts;

use Illuminate\Support\Collection;

interface OnboardingSetupNotificationReader
{
    /** @return Collection<int, array<string, mixed>> */
    public function notifications(string $userId): Collection;

    /** @return array<string, mixed> */
    public function markRead(string $userId, string $notificationId): array;
}
