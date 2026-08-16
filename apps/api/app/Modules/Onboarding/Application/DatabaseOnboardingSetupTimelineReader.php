<?php

declare(strict_types=1);

namespace App\Modules\Onboarding\Application;

use App\Modules\Onboarding\Application\Contracts\OnboardingSetupTimelineReader;
use App\Modules\Onboarding\Domain\OnboardingSetupEvent;
use App\Modules\Tenancy\Application\TenantContext;
use Illuminate\Support\Collection;

final readonly class DatabaseOnboardingSetupTimelineReader implements OnboardingSetupTimelineReader
{
    public function __construct(private TenantContext $context) {}

    public function events(): Collection
    {
        return OnboardingSetupEvent::query()
            ->where('tenant_id', (string) $this->context->id())
            ->orderByDesc('occurred_at')
            ->orderByDesc('id')
            ->limit(100)
            ->get()
            ->map(static fn (OnboardingSetupEvent $event): array => [
                'id' => (string) $event->getKey(),
                'type' => (string) $event->type,
                'status' => (string) $event->status,
                'message' => (string) $event->message,
                'runId' => $event->run_id === null ? null : (string) $event->run_id,
                'correlationId' => (string) $event->correlation_id,
                'metadata' => $event->metadata ?? [],
                'occurredAt' => $event->occurred_at?->format(DATE_ATOM),
            ])->values();
    }
}
