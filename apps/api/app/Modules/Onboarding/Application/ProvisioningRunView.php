<?php

declare(strict_types=1);

namespace App\Modules\Onboarding\Application;

final readonly class ProvisioningRunView
{
    /** @param array<string, mixed> $plan @param list<array<string, mixed>> $actions */
    public function __construct(
        public string $id,
        public string $status,
        public bool $dryRun,
        public bool $approvalRequired,
        public string $correlationId,
        public array $plan,
        public array $actions,
        public ?string $approvedAt = null,
        public ?string $completedAt = null,
    ) {}

    /** @return array<string, mixed> */
    public function toArray(): array
    {
        return [
            'id' => $this->id,
            'status' => $this->status,
            'dryRun' => $this->dryRun,
            'approvalRequired' => $this->approvalRequired,
            'correlationId' => $this->correlationId,
            'plan' => $this->plan,
            'actions' => $this->actions,
            'approvedAt' => $this->approvedAt,
            'completedAt' => $this->completedAt,
        ];
    }
}
