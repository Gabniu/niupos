<?php

declare(strict_types=1);

namespace App\Modules\Onboarding\Application\Contracts;

use App\Modules\Onboarding\Application\ProvisioningExecutionContext;
use App\Modules\Onboarding\Application\ProvisioningExecutionResult;

interface OnboardingExternalProvisioningExecutor
{
    public function supports(string $actionCode): bool;

    public function execute(ProvisioningExecutionContext $context): ProvisioningExecutionResult;
}
