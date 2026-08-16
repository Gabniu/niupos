<?php

declare(strict_types=1);

namespace App\Modules\Onboarding\Application;

use App\Modules\Onboarding\Domain\ChannelSelection;

final readonly class OnboardingDraftView
{
    /**
     * @param array<string, mixed> $answers
     * @param list<string> $automated
     * @param list<string> $ownerApprovals
     */
    public function __construct(
        public string $id,
        public ?ChannelSelection $channelSelection,
        public ?string $industryProfile,
        public array $answers,
        public int $revision,
        public string $status,
        public string $nextStep,
        public array $automated,
        public array $ownerApprovals,
        public ?string $tenantId = null,
        public ?string $completedAt = null,
        public ?string $companyId = null,
        public ?string $branchId = null,
        public ?string $warehouseId = null,
        public ?string $registerId = null,
    ) {}

    /** @return array<string, mixed> */
    public function toArray(): array
    {
        return [
            'id' => $this->id,
            'channelSelection' => $this->channelSelection?->value,
            'industryProfile' => $this->industryProfile,
            'answers' => $this->answers,
            'revision' => $this->revision,
            'status' => $this->status,
            'nextStep' => $this->nextStep,
            'plan' => [
                'automated' => $this->automated,
                'ownerApprovals' => $this->ownerApprovals,
            ],
            'tenantId' => $this->tenantId,
            'completedAt' => $this->completedAt,
            'location' => [
                'companyId' => $this->companyId,
                'branchId' => $this->branchId,
                'warehouseId' => $this->warehouseId,
                'registerId' => $this->registerId,
            ],
        ];
    }
}
