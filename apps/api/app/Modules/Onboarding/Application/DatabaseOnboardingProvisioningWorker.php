<?php

declare(strict_types=1);

namespace App\Modules\Onboarding\Application;

use App\Modules\Audit\Application\Contracts\SecurityAuditRecorder;
use App\Modules\Audit\Application\SecurityAuditEvent;
use App\Modules\Onboarding\Application\Contracts\OnboardingProvisioningWorker;
use App\Modules\Onboarding\Application\Contracts\OnboardingSetupNotificationWriter;
use App\Modules\Onboarding\Application\Contracts\OnboardingProvisioningExecutorRegistry;
use App\Modules\Onboarding\Domain\OnboardingProvisioningAction;
use App\Modules\Onboarding\Domain\OnboardingProvisioningRun;
use App\Modules\Onboarding\Domain\OnboardingSetupEvent;
use App\Modules\Tenancy\Application\TenantContext;
use DomainException;
use Illuminate\Support\Facades\DB;

final readonly class DatabaseOnboardingProvisioningWorker implements OnboardingProvisioningWorker
{
    public function __construct(private TenantContext $context, private SecurityAuditRecorder $audit, private OnboardingSetupNotificationWriter $notifications, private OnboardingProvisioningExecutorRegistry $executors) {}

    public function process(string $userId, string $runId): ProvisioningRunView
    {
        $tenantId = (string) $this->context->id();

        return DB::transaction(function () use ($tenantId, $userId, $runId): ProvisioningRunView {
            $run = OnboardingProvisioningRun::query()
                ->where('tenant_id', $tenantId)
                ->where('initiated_by_user_id', $userId)
                ->whereKey($runId)
                ->lockForUpdate()
                ->first();
            if (! $run instanceof OnboardingProvisioningRun) {
                throw new DomainException('The provisioning run was not found.');
            }
            if ($run->status === 'needs_action') {
                return $this->view($run);
            }
            if ($run->status !== 'queued') {
                throw new DomainException('Only a queued provisioning run can be processed.');
            }

            $actions = OnboardingProvisioningAction::query()
                ->where('tenant_id', $tenantId)
                ->where('run_id', $run->getKey())
                ->where('status', 'queued')
                ->lockForUpdate()
                ->get();
            foreach ($actions as $action) {
                $executor = $this->executors->executorFor((string) $action->code);
                if ($executor === 'tenant.workspace_preferences') {
                    $exists = DB::table('tenant_workspace_preferences')->where('tenant_id', $tenantId)->exists();
                    if (! $exists) {
                        DB::table('tenant_workspace_preferences')->insert([
                            'tenant_id' => $tenantId,
                            'side_panel_visible' => true,
                            'kiosk_mode' => false,
                            'created_at' => now(),
                            'updated_at' => now(),
                        ]);
                    }
                    $action->forceFill([
                        'status' => 'succeeded',
                        'result' => ['executor' => $executor, 'initialized' => ! $exists, 'externalSideEffects' => false],
                    ])->save();
                    continue;
                }
                if ($executor === 'onboarding.notification_preferences') {
                    $exists = DB::table('onboarding_notification_preferences')->where('tenant_id', $tenantId)->exists();
                    if (! $exists) {
                        DB::table('onboarding_notification_preferences')->insert([
                            'tenant_id' => $tenantId,
                            'in_app_enabled' => true,
                            'email_enabled' => false,
                            'sms_enabled' => false,
                            'push_enabled' => false,
                            'created_at' => now(),
                            'updated_at' => now(),
                        ]);
                    }
                    $action->forceFill([
                        'status' => 'succeeded',
                        'result' => ['executor' => $executor, 'initialized' => ! $exists, 'externalSideEffects' => false],
                    ])->save();
                    continue;
                }
                // No external adapter is registered in this slice. Fail closed
                // instead of claiming that a domain, payment, build, or
                // notification side effect completed.
                $action->forceFill([
                    'status' => 'needs_action',
                    'error_message' => 'No verified executor is registered for this action.',
                    'result' => ['executor' => 'unregistered', 'externalSideEffects' => false],
                ])->save();
            }
            $blocked = $actions->contains(static fn (OnboardingProvisioningAction $action): bool => $action->status === 'needs_action');
            if (! $blocked) {
                $run->forceFill([
                    'status' => 'completed',
                    'completed_at' => now(),
                    'failure_message' => null,
                ])->save();
                $this->audit->record(new SecurityAuditEvent(
                    'onboarding.provisioning.completed',
                    $userId,
                    ['run_id' => (string) $run->getKey(), 'correlation_id' => (string) $run->correlation_id],
                    $tenantId,
                ));
                $event = OnboardingSetupEvent::query()->create([
                    'tenant_id' => $tenantId,
                    'actor_user_id' => $userId,
                    'run_id' => $run->getKey(),
                    'type' => 'provisioning.completed',
                    'status' => 'succeeded',
                    'message' => 'Provisioning completed using verified internal executors.',
                    'correlation_id' => $run->correlation_id,
                    'metadata' => ['externalSideEffects' => false],
                    'occurred_at' => now(),
                ]);
                $this->notifications->fromEvent($event);

                return $this->view($run->refresh());
            }

            $run->forceFill([
                'status' => 'needs_action',
                'failure_message' => 'Provisioning is paused until verified action executors are configured.',
            ])->save();
            $this->audit->record(new SecurityAuditEvent(
                'onboarding.provisioning.worker_blocked',
                $userId,
                ['run_id' => (string) $run->getKey(), 'correlation_id' => (string) $run->correlation_id],
                $tenantId,
            ));
            $event = OnboardingSetupEvent::query()->create([
                'tenant_id' => $tenantId,
                'actor_user_id' => $userId,
                'run_id' => $run->getKey(),
                'type' => 'provisioning.worker_blocked',
                'status' => 'needs_action',
                'message' => 'Provisioning is paused until verified action executors are configured.',
                'correlation_id' => $run->correlation_id,
                'metadata' => ['externalSideEffects' => false],
                'occurred_at' => now(),
            ]);
            $this->notifications->fromEvent($event);

            return $this->view($run->refresh());
        });
    }

    private function view(OnboardingProvisioningRun $run): ProvisioningRunView
    {
        $actions = OnboardingProvisioningAction::query()
            ->where('tenant_id', (string) $this->context->id())
            ->where('run_id', $run->getKey())
            ->orderBy('sequence')
            ->get()
            ->map(static fn (OnboardingProvisioningAction $action): array => [
                'id' => (string) $action->getKey(),
                'sequence' => (int) $action->sequence,
                'code' => (string) $action->code,
                'status' => (string) $action->status,
                'requiresApproval' => (bool) $action->requires_approval,
                'reversible' => (bool) $action->reversible,
                'details' => $action->details ?? [],
                'result' => $action->result,
            ])->values()->all();

        return new ProvisioningRunView(
            (string) $run->getKey(),
            (string) $run->status,
            (bool) $run->dry_run,
            (bool) $run->approval_required,
            (string) $run->correlation_id,
            $run->plan ?? [],
            $actions,
            $run->approved_at?->format(DATE_ATOM),
            $run->completed_at?->format(DATE_ATOM),
        );
    }
}
