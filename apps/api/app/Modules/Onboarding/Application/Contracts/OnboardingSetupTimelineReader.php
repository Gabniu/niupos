<?php

declare(strict_types=1);

namespace App\Modules\Onboarding\Application\Contracts;

use Illuminate\Support\Collection;

interface OnboardingSetupTimelineReader
{
    /** @return Collection<int, array<string, mixed>> */
    public function events(): Collection;
}
