<?php

declare(strict_types=1);

namespace App\Modules\Onboarding\Application;

use App\Modules\Onboarding\Application\Contracts\OnboardingProvisioningExecutorRegistry;

final class DatabaseOnboardingProvisioningExecutorRegistry implements OnboardingProvisioningExecutorRegistry
{
    public function capabilities(): array
    {
        return [
            ['code' => 'workspace.navigation_defaults', 'executor' => 'tenant.workspace_preferences', 'available' => true, 'externalSideEffects' => false],
            ['code' => 'notifications.setup', 'executor' => 'onboarding.notification_preferences', 'available' => true, 'externalSideEffects' => false],
            ['code' => 'web.storefront.publication', 'executor' => null, 'available' => false, 'externalSideEffects' => true],
            ['code' => 'mobile.build.release', 'executor' => null, 'available' => false, 'externalSideEffects' => true],
        ];
    }

    public function executorFor(string $actionCode): ?string
    {
        foreach ($this->capabilities() as $capability) {
            if ($capability['code'] === $actionCode && $capability['available']) {
                return $capability['executor'];
            }
        }

        return null;
    }
}
