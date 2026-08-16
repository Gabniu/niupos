<?php

declare(strict_types=1);

namespace App\Modules\Onboarding\Application;

final readonly class ProvisioningExecutionResult
{
    /** @param array<string, mixed> $evidence */
    public function __construct(
        public string $status,
        public string $message,
        public bool $externalSideEffects,
        public array $evidence = [],
    ) {}
}
