<?php

declare(strict_types=1);

namespace App\Modules\Onboarding\Application;

use App\Modules\Audit\Application\SecurityAuditEvent;
use App\Modules\Audit\Application\Contracts\SecurityAuditRecorder;
use App\Modules\Onboarding\Application\Contracts\OnboardingProvisioningManager;
use App\Modules\Onboarding\Application\Contracts\OnboardingSetupNotificationWriter;
use App\Modules\Onboarding\Domain\ChannelSelection;
use App\Modules\Onboarding\Domain\OnboardingDraft;
use App\Modules\Onboarding\Domain\OnboardingProvisioningAction;
use App\Modules\Onboarding\Domain\OnboardingProvisioningRun;
use App\Modules\Onboarding\Domain\OnboardingSetupEvent;
use App\Modules\Tenancy\Application\TenantContext;
use DomainException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

final readonly class DatabaseOnboardingProvisioningManager implements OnboardingProvisioningManager
{
    public function __construct(
        private TenantContext $context,
        private SecurityAuditRecorder $audit,
        private OnboardingSetupNotificationWriter $notifications,
    ) {}

    public function preview(string $userId, string $idempotencyKey): ProvisioningRunView
    {
        $idempotencyKey = trim($idempotencyKey);
        if ($idempotencyKey === '' || strlen($idempotencyKey) > 128) {
            throw new DomainException('A bounded idempotency key is required.');
        }

        $tenantId = (string) $this->context->id();

        return DB::transaction(function () use ($tenantId, $userId, $idempotencyKey): ProvisioningRunView {
            $draft = OnboardingDraft::query()
                ->where('tenant_id', $tenantId)
                ->where('user_id', $userId)
                ->lockForUpdate()
                ->first();
            if (! $draft instanceof OnboardingDraft || $draft->channel_selection === null) {
                throw new DomainException('Complete onboarding before requesting a provisioning preview.');
            }

            $fingerprint = hash('sha256', json_encode([
                'draftId' => (string) $draft->getKey(),
                'revision' => (int) $draft->revision,
                'channel' => (string) $draft->channel_selection,
                'industry' => (string) $draft->industry_profile,
            ], JSON_THROW_ON_ERROR));
            $existing = OnboardingProvisioningRun::query()
                ->where('tenant_id', $tenantId)
                ->where('idempotency_key', $idempotencyKey)
                ->lockForUpdate()
                ->first();
            if ($existing instanceof OnboardingProvisioningRun) {
                if (! hash_equals((string) $existing->command_fingerprint, $fingerprint)) {
                    throw new DomainException('The idempotency key is already bound to another provisioning plan.');
                }

                return $this->view($existing);
            }

            $plan = $this->plan(ChannelSelection::from((string) $draft->channel_selection), (string) $draft->industry_profile);
            $approvalRequired = collect($plan['actions'])->contains(static fn (array $action): bool => $action['requiresApproval'] === true);
            $run = OnboardingProvisioningRun::query()->create([
                'tenant_id' => $tenantId,
                'initiated_by_user_id' => $userId,
                'idempotency_key' => $idempotencyKey,
                'command_fingerprint' => $fingerprint,
                'status' => $approvalRequired ? 'needs_action' : 'queued',
                'dry_run' => true,
                'approval_required' => $approvalRequired,
                'plan' => [
                    'blueprintVersion' => '2026-08-16.v1',
                    'channelSelection' => (string) $draft->channel_selection,
                    'industryProfile' => $draft->industry_profile,
                    'automated' => $plan['automated'],
                    'ownerApprovals' => $plan['ownerApprovals'],
                ],
                'correlation_id' => (string) Str::uuid(),
            ]);

            foreach ($plan['actions'] as $sequence => $action) {
                OnboardingProvisioningAction::query()->create([
                    'tenant_id' => $tenantId,
                    'run_id' => $run->getKey(),
                    'sequence' => $sequence + 1,
                    'code' => $action['code'],
                    'status' => $action['requiresApproval'] ? 'needs_approval' : 'queued',
                    'requires_approval' => $action['requiresApproval'],
                    'reversible' => $action['reversible'],
                    'details' => ['reason' => $action['reason']],
                ]);
            }

            $this->audit->record(new SecurityAuditEvent(
                'onboarding.provisioning.previewed',
                $userId,
                ['run_id' => (string) $run->getKey(), 'correlation_id' => (string) $run->correlation_id, 'dry_run' => true],
                $tenantId,
            ));
            $this->event($tenantId, $userId, (string) $run->getKey(), (string) $run->correlation_id, 'provisioning.previewed', (string) $run->status, 'A dry-run setup plan is ready for review.');

            return $this->view($run->refresh());
        });
    }

    public function find(string $userId, string $runId): ProvisioningRunView
    {
        $run = OnboardingProvisioningRun::query()
            ->where('tenant_id', (string) $this->context->id())
            ->where('initiated_by_user_id', $userId)
            ->whereKey($runId)
            ->first();
        if (! $run instanceof OnboardingProvisioningRun) {
            throw new DomainException('The provisioning run was not found.');
        }

        return $this->view($run);
    }

    public function approve(string $userId, string $runId, string $approvalReference): ProvisioningRunView
    {
        $approvalReference = trim($approvalReference);
        if ($approvalReference === '' || strlen($approvalReference) > 128) {
            throw new DomainException('A bounded approval reference is required.');
        }

        $tenantId = (string) $this->context->id();

        return DB::transaction(function () use ($tenantId, $userId, $runId, $approvalReference): ProvisioningRunView {
            $run = OnboardingProvisioningRun::query()
                ->where('tenant_id', $tenantId)
                ->where('initiated_by_user_id', $userId)
                ->whereKey($runId)
                ->lockForUpdate()
                ->first();
            if (! $run instanceof OnboardingProvisioningRun) {
                throw new DomainException('The provisioning run was not found.');
            }
            if ($run->status === 'queued') {
                return $this->view($run);
            }
            if ($run->status !== 'needs_action') {
                throw new DomainException('Only a provisioning run awaiting action can be approved.');
            }

            OnboardingProvisioningAction::query()
                ->where('tenant_id', $tenantId)
                ->where('run_id', $run->getKey())
                ->where('status', 'needs_approval')
                ->update(['status' => 'queued', 'result' => ['approvalReferenceHash' => hash('sha256', $approvalReference)]]);
            $run->forceFill([
                'status' => 'queued',
                'approved_by_user_id' => $userId,
                'approved_at' => now(),
            ])->save();
            $this->audit->record(new SecurityAuditEvent(
                'onboarding.provisioning.approved',
                $userId,
                ['run_id' => (string) $run->getKey(), 'correlation_id' => (string) $run->correlation_id],
                $tenantId,
            ));
            $this->event($tenantId, $userId, (string) $run->getKey(), (string) $run->correlation_id, 'provisioning.approved', 'queued', 'The approved setup plan is queued for a verified worker.');

            return $this->view($run->refresh());
        });
    }

    /** @return array{automated: list<string>, ownerApprovals: list<string>, actions: list<array{code: string, reason: string, requiresApproval: bool, reversible: bool}>} */
    private function plan(ChannelSelection $channel, string $industry): array
    {
        $actions = [
            ['code' => 'workspace.navigation_defaults', 'reason' => 'Apply the selected industry navigation without creating business records.', 'requiresApproval' => false, 'reversible' => true],
            ['code' => 'notifications.setup', 'reason' => 'Prepare setup-event routing without sending credentials or customer data.', 'requiresApproval' => false, 'reversible' => true],
        ];
        $automated = ['Apply blueprint '.$industry, 'Prepare tenant-safe navigation defaults', 'Create an auditable setup timeline'];
        $ownerApprovals = ['Review the generated setup diff'];

        if ($channel->includesWeb()) {
            $actions[] = ['code' => 'web.storefront.publication', 'reason' => 'Domain, payment, and storefront publication require owner approval.', 'requiresApproval' => true, 'reversible' => true];
            $ownerApprovals[] = 'Approve storefront domain, payments, and publication';
        }
        if ($channel->includesMobile()) {
            $actions[] = ['code' => 'mobile.build.release', 'reason' => 'App signing, store submission, and release require owner approval.', 'requiresApproval' => true, 'reversible' => true];
            $ownerApprovals[] = 'Approve app signing, store submission, and release';
        }

        return compact('automated', 'ownerApprovals', 'actions');
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

    private function event(string $tenantId, string $userId, string $runId, string $correlationId, string $type, string $status, string $message): void
    {
        $event = OnboardingSetupEvent::query()->create([
            'tenant_id' => $tenantId,
            'actor_user_id' => $userId,
            'run_id' => $runId,
            'type' => $type,
            'status' => $status,
            'message' => $message,
            'correlation_id' => $correlationId,
            'metadata' => ['externalSideEffects' => false],
            'occurred_at' => now(),
        ]);
        $this->notifications->fromEvent($event);
    }
}
