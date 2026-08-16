<?php

declare(strict_types=1);

namespace App\Modules\Onboarding\Application\Http;

use App\Modules\Onboarding\Application\Contracts\OnboardingProvisioningManager;
use App\Modules\Onboarding\Application\Contracts\OnboardingSetupTimelineReader;
use App\Modules\Onboarding\Application\Contracts\OnboardingProvisioningWorker;
use App\Modules\Onboarding\Application\Contracts\OnboardingSetupNotificationReader;
use App\Modules\Onboarding\Application\Contracts\OnboardingProvisioningExecutorRegistry;
use App\Modules\Onboarding\Application\Contracts\OnboardingNotificationDeliveryDispatcher;
use App\Modules\Tenancy\Application\TenantContext;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpFoundation\Response;
use Throwable;

final readonly class OnboardingProvisioningController
{
    public function __construct(private OnboardingProvisioningManager $runs, private OnboardingSetupTimelineReader $timeline, private OnboardingProvisioningWorker $worker, private OnboardingSetupNotificationReader $notifications, private OnboardingProvisioningExecutorRegistry $executors, private OnboardingNotificationDeliveryDispatcher $deliveryDispatcher, private TenantContext $context) {}

    public function capabilities(): JsonResponse
    {
        return new JsonResponse(['data' => $this->executors->capabilities()]);
    }

    public function notifications(Request $request): JsonResponse
    {
        return new JsonResponse(['data' => $this->notifications->notifications($this->userId($request))->values()->all()]);
    }

    public function markNotificationRead(Request $request, string $notificationId): JsonResponse
    {
        try {
            $notification = $this->notifications->markRead($this->userId($request), $notificationId);
        } catch (Throwable) {
            return new JsonResponse(['error' => ['code' => 'SETUP_NOTIFICATION_NOT_FOUND', 'message' => 'The setup notification was not found.']], Response::HTTP_NOT_FOUND);
        }

        return new JsonResponse(['data' => $notification]);
    }

    public function notificationPreferences(): JsonResponse
    {
        $row = DB::table('onboarding_notification_preferences')
            ->where('tenant_id', (string) $this->context->id())
            ->first();

        return new JsonResponse(['data' => [
            'inAppEnabled' => $row === null ? true : (bool) $row->in_app_enabled,
            'emailEnabled' => $row !== null && (bool) $row->email_enabled,
            'smsEnabled' => $row !== null && (bool) $row->sms_enabled,
            'pushEnabled' => $row !== null && (bool) $row->push_enabled,
            'quietStart' => $row?->quiet_start,
            'quietEnd' => $row?->quiet_end,
            'externalDeliveryAvailable' => (bool) config('services.resend.onboarding_enabled', false)
                && trim((string) config('services.resend.key', '')) !== ''
                && trim((string) config('services.resend.from', '')) !== '',
        ]]);
    }

    public function notificationDeliveries(Request $request): JsonResponse
    {
        $rows = DB::table('onboarding_notification_deliveries as deliveries')
            ->join('onboarding_setup_notifications as notifications', function ($join): void {
                $join->on('notifications.id', '=', 'deliveries.notification_id')
                    ->on('notifications.tenant_id', '=', 'deliveries.tenant_id');
            })
            ->where('deliveries.tenant_id', (string) $this->context->id())
            ->where('deliveries.recipient_user_id', $this->userId($request))
            ->orderByDesc('deliveries.created_at')
            ->limit(100)
            ->get([
                'deliveries.id', 'deliveries.channel', 'deliveries.status',
                'deliveries.blocked_reason', 'deliveries.sent_at', 'deliveries.attempts',
                'deliveries.created_at', 'notifications.title',
                'notifications.message',
            ])
            ->map(static fn (object $row): array => [
                'id' => (string) $row->id,
                'channel' => (string) $row->channel,
                'status' => (string) $row->status,
                'blockedReason' => $row->blocked_reason,
                'attempts' => (int) $row->attempts,
                'title' => (string) $row->title,
                'message' => (string) $row->message,
                'sentAt' => $row->sent_at,
                'createdAt' => $row->created_at,
            ])
            ->values()
            ->all();

        return new JsonResponse(['data' => $rows]);
    }

    public function sendNotificationDelivery(Request $request, string $deliveryId): JsonResponse
    {
        try {
            $result = $this->deliveryDispatcher->dispatch($this->userId($request), $deliveryId);
        } catch (Throwable) {
            return new JsonResponse(['error' => ['code' => 'NOTIFICATION_DELIVERY_FAILED', 'message' => 'The message could not be sent.']], Response::HTTP_UNPROCESSABLE_ENTITY);
        }

        $status = $result->status === 'sent' ? Response::HTTP_OK : Response::HTTP_UNPROCESSABLE_ENTITY;

        return new JsonResponse(['data' => [
            'status' => $result->status,
            'message' => $result->message,
            'evidence' => $result->evidence,
        ]], $status);
    }

    public function updateNotificationPreferences(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'inAppEnabled' => ['required', 'boolean'],
            'emailEnabled' => ['required', 'boolean'],
            'smsEnabled' => ['required', 'boolean'],
            'pushEnabled' => ['required', 'boolean'],
            'quietStart' => ['nullable', 'date_format:H:i'],
            'quietEnd' => ['nullable', 'date_format:H:i'],
        ]);
        $tenantId = (string) $this->context->id();
        $values = [
            'in_app_enabled' => (bool) $validated['inAppEnabled'],
            'email_enabled' => (bool) $validated['emailEnabled'],
            'sms_enabled' => (bool) $validated['smsEnabled'],
            'push_enabled' => (bool) $validated['pushEnabled'],
            'quiet_start' => $validated['quietStart'] ?? null,
            'quiet_end' => $validated['quietEnd'] ?? null,
            'updated_at' => now(),
        ];
        if (DB::table('onboarding_notification_preferences')->where('tenant_id', $tenantId)->exists()) {
            DB::table('onboarding_notification_preferences')->where('tenant_id', $tenantId)->update($values);
        } else {
            DB::table('onboarding_notification_preferences')->insert($values + ['tenant_id' => $tenantId, 'created_at' => now()]);
        }

        return $this->notificationPreferences();
    }

    public function process(Request $request, string $runId): JsonResponse
    {
        try {
            $run = $this->worker->process($this->userId($request), $runId);
        } catch (Throwable $exception) {
            return new JsonResponse(['error' => ['code' => 'PROVISIONING_PROCESSING_INVALID', 'message' => $exception->getMessage()]], Response::HTTP_UNPROCESSABLE_ENTITY);
        }

        return new JsonResponse(['data' => $run->toArray()]);
    }

    public function timeline(): JsonResponse
    {
        return new JsonResponse(['data' => $this->timeline->events()->values()->all()]);
    }

    public function preview(Request $request): JsonResponse
    {
        $key = $request->header('Idempotency-Key');
        if (! is_string($key) || trim($key) === '') {
            return new JsonResponse(['error' => ['code' => 'PROVISIONING_IDEMPOTENCY_REQUIRED', 'message' => 'An Idempotency-Key is required.']], Response::HTTP_UNPROCESSABLE_ENTITY);
        }

        try {
            $run = $this->runs->preview($this->userId($request), $key);
        } catch (Throwable $exception) {
            return new JsonResponse(['error' => ['code' => 'PROVISIONING_PREVIEW_INVALID', 'message' => $exception->getMessage()]], Response::HTTP_UNPROCESSABLE_ENTITY);
        }

        return new JsonResponse(['data' => $run->toArray()], Response::HTTP_CREATED);
    }

    public function show(Request $request, string $runId): JsonResponse
    {
        try {
            $run = $this->runs->find($this->userId($request), $runId);
        } catch (Throwable $exception) {
            return new JsonResponse(['error' => ['code' => 'PROVISIONING_NOT_FOUND', 'message' => 'The provisioning run was not found.']], Response::HTTP_NOT_FOUND);
        }

        return new JsonResponse(['data' => $run->toArray()]);
    }

    public function approve(Request $request, string $runId): JsonResponse
    {
        $reference = $request->header('Idempotency-Key');
        if (! is_string($reference) || trim($reference) === '') {
            return new JsonResponse(['error' => ['code' => 'PROVISIONING_APPROVAL_REFERENCE_REQUIRED', 'message' => 'An approval reference is required.']], Response::HTTP_UNPROCESSABLE_ENTITY);
        }

        try {
            $run = $this->runs->approve($this->userId($request), $runId, $reference);
        } catch (Throwable $exception) {
            return new JsonResponse(['error' => ['code' => 'PROVISIONING_APPROVAL_INVALID', 'message' => $exception->getMessage()]], Response::HTTP_UNPROCESSABLE_ENTITY);
        }

        return new JsonResponse(['data' => $run->toArray()]);
    }

    private function userId(Request $request): string
    {
        $id = $request->user()?->getAuthIdentifier();
        abort_unless(is_string($id) && $id !== '', Response::HTTP_UNAUTHORIZED);

        return $id;
    }
}
