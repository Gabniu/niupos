<?php

declare(strict_types=1);

namespace App\Modules\Onboarding\Application\Contracts;

use App\Modules\Onboarding\Application\OnboardingDraftView;

interface OnboardingDraftManager
{
    public function find(string $userId): ?OnboardingDraftView;

    /** @param array<string, mixed> $changes */
    public function save(string $userId, array $changes, int $expectedRevision, string $idempotencyKey): OnboardingDraftView;

    public function completePos(string $userId, int $expectedRevision, string $idempotencyKey): OnboardingDraftView;

    public function completeOrganization(string $userId, int $expectedRevision, string $idempotencyKey): OnboardingDraftView;

    /** @param array<string, string> $setup */
    public function completePosLocations(string $userId, array $setup, int $expectedRevision, string $idempotencyKey): OnboardingDraftView;
}
