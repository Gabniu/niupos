<?php

declare(strict_types=1);

namespace App\Modules\Onboarding\Application\Contracts;

interface OnboardingProvisioningExecutorRegistry
{
    /** @return list<array{code: string, executor: string|null, available: bool, externalSideEffects: bool}> */
    public function capabilities(): array;

    public function executorFor(string $actionCode): ?string;
}
