<?php

declare(strict_types=1);

namespace App\Modules\Onboarding\Application;

use App\Modules\Onboarding\Application\Contracts\OnboardingSetupNotificationReader;
use App\Modules\Onboarding\Domain\OnboardingSetupNotification;
use App\Modules\Tenancy\Application\TenantContext;
use DomainException;
use Illuminate\Support\Collection;

final readonly class DatabaseOnboardingSetupNotificationReader implements OnboardingSetupNotificationReader
{
    public function __construct(private TenantContext $context) {}

    public function notifications(string $userId): Collection
    {
        return OnboardingSetupNotification::query()
            ->where('tenant_id', (string) $this->context->id())
            ->where('recipient_user_id', $userId)
            ->orderByDesc('created_at')
            ->orderByDesc('id')
            ->limit(100)
            ->get()
            ->map(fn (OnboardingSetupNotification $notification): array => $this->view($notification))
            ->values();
    }

    public function markRead(string $userId, string $notificationId): array
    {
        $notification = OnboardingSetupNotification::query()
            ->where('tenant_id', (string) $this->context->id())
            ->where('recipient_user_id', $userId)
            ->whereKey($notificationId)
            ->first();
        if (! $notification instanceof OnboardingSetupNotification) {
            throw new DomainException('The setup notification was not found.');
        }

        if ($notification->read_at === null) {
            $notification->forceFill(['read_at' => now()])->save();
        }

        return $this->view($notification->refresh());
    }

    /** @return array<string, mixed> */
    private function view(OnboardingSetupNotification $notification): array
    {
        return [
            'id' => (string) $notification->getKey(),
            'type' => (string) $notification->type,
            'title' => (string) $notification->title,
            'message' => (string) $notification->message,
            'runId' => $notification->run_id === null ? null : (string) $notification->run_id,
            'readAt' => $notification->read_at?->format(DATE_ATOM),
            'createdAt' => $notification->created_at?->format(DATE_ATOM),
        ];
    }
}
