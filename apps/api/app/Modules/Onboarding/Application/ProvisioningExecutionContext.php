<?php

declare(strict_types=1);

namespace App\Modules\Onboarding\Application;

final readonly class ProvisioningExecutionContext
{
    /** @param array<string, mixed> $details */
    public function __construct(
        public string $tenantId,
        public string $runId,
        public string $actionId,
        public string $actionCode,
        public string $correlationId,
        public array $details,
    ) {}
}
