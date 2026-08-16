<?php

declare(strict_types=1);

namespace App\Modules\Onboarding\Application\Contracts;

use App\Modules\Onboarding\Application\ProvisioningRunView;

interface OnboardingProvisioningManager
{
    public function preview(string $userId, string $idempotencyKey): ProvisioningRunView;

    public function find(string $userId, string $runId): ProvisioningRunView;

    public function approve(string $userId, string $runId, string $approvalReference): ProvisioningRunView;
}
