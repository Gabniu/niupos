<?php

declare(strict_types=1);

namespace App\Modules\Onboarding\Application\Contracts;

use App\Modules\Onboarding\Application\ProvisioningRunView;

interface OnboardingProvisioningWorker
{
    public function process(string $userId, string $runId): ProvisioningRunView;
}
