<?php

declare(strict_types=1);

namespace App\Modules\Onboarding\Application;

use App\Modules\Onboarding\Application\Contracts\OnboardingDraftManager;
use App\Modules\Channels\Application\Contracts\ChannelRegistrationManager;
use App\Modules\Channels\Application\ChannelRegistrationView;
use App\Modules\Identity\Application\Contracts\OwnerMembershipProvisioner;
use App\Modules\Register\Application\Contracts\RegisterDeviceManager;
use App\Modules\Tenancy\Application\Contracts\OrganizationLocations;
use App\Modules\Tenancy\Application\Contracts\TenantCreator;
use App\Modules\Tenancy\Application\TenantScope;
use App\Modules\Onboarding\Domain\ChannelSelection;
use App\Modules\Onboarding\Domain\OnboardingDraft;
use DomainException;
use Illuminate\Support\Facades\DB;

final readonly class DatabaseOnboardingDraftManager implements OnboardingDraftManager
{
    private const LAUNCH_JURISDICTION = 'KE';

    public function __construct(
        private TenantCreator $tenants,
        private OwnerMembershipProvisioner $owners,
        private OrganizationLocations $locations,
        private RegisterDeviceManager $registers,
        private TenantScope $tenantScope,
        private ChannelRegistrationManager $channels,
    ) {}

    public function find(string $userId): ?OnboardingDraftView
    {
        $draft = OnboardingDraft::query()->where('user_id', $userId)->first();

        return $draft instanceof OnboardingDraft ? $this->view($draft) : null;
    }

    public function save(string $userId, array $changes, int $expectedRevision, string $idempotencyKey): OnboardingDraftView
    {
        $idempotencyKey = trim($idempotencyKey);
        if ($idempotencyKey === '' || strlen($idempotencyKey) > 128) {
            throw new DomainException('A bounded idempotency key is required.');
        }

        return DB::transaction(function () use ($userId, $changes, $expectedRevision, $idempotencyKey): OnboardingDraftView {
            $draft = OnboardingDraft::query()->where('user_id', $userId)->lockForUpdate()->first();

            if ($draft instanceof OnboardingDraft && $draft->last_idempotency_key === $idempotencyKey) {
                return $this->view($draft);
            }

            if ($draft instanceof OnboardingDraft && $draft->register_id !== null) {
                throw new DomainException('This onboarding workspace has already been created.');
            }

            if ($draft instanceof OnboardingDraft && $draft->revision !== $expectedRevision) {
                throw new DomainException('The onboarding draft changed. Refresh before saving again.');
            }

            $values = [
                'channel_selection' => $this->channel($changes['channelSelection'] ?? ($draft?->channel_selection)),
                'industry_profile' => $this->industry($changes['industryProfile'] ?? ($draft?->industry_profile)),
                'answers' => array_merge($draft?->answers ?? [], is_array($changes['answers'] ?? null) ? $changes['answers'] : []),
                'current_step' => is_string($changes['currentStep'] ?? null) ? trim($changes['currentStep']) : ($draft?->current_step ?? 'channel'),
                'revision' => ($draft?->revision ?? 0) + 1,
                'last_idempotency_key' => $idempotencyKey,
                'status' => $draft?->tenant_id !== null ? 'completed' : 'in_progress',
            ];

            if ($draft instanceof OnboardingDraft) {
                $draft->fill($values);
                $draft->save();
            } else {
                $draft = OnboardingDraft::query()->create($values + ['user_id' => $userId]);
            }

            return $this->view($draft->refresh());
        });
    }

    public function completePos(string $userId, int $expectedRevision, string $idempotencyKey): OnboardingDraftView
    {
        return $this->completeOrganization($userId, $expectedRevision, $idempotencyKey);
    }

    public function completeOrganization(string $userId, int $expectedRevision, string $idempotencyKey): OnboardingDraftView
    {
        $idempotencyKey = trim($idempotencyKey);
        if ($idempotencyKey === '' || strlen($idempotencyKey) > 128) {
            throw new DomainException('A bounded idempotency key is required.');
        }

        return DB::transaction(function () use ($userId, $expectedRevision, $idempotencyKey): OnboardingDraftView {
            $draft = OnboardingDraft::query()->where('user_id', $userId)->lockForUpdate()->first();
            if (! $draft instanceof OnboardingDraft) {
                throw new DomainException('Complete the onboarding questions first.');
            }
            if ($draft->tenant_id !== null) {
                return $this->view($draft);
            }
            if ($draft->revision !== $expectedRevision) {
                throw new DomainException('The onboarding draft changed. Refresh before completing it.');
            }
            if ($draft->channel_selection === null) {
                throw new DomainException('Choose an operating channel before creating the organization.');
            }

            $organizationName = trim((string) ($draft->answers['organizationName'] ?? ''));
            if ($organizationName === '') {
                throw new DomainException('An organization name is required before workspace creation.');
            }

            // The first launch profile is Kenya; a later jurisdiction step will
            // replace this constant before additional country profiles activate.
            $tenant = $this->tenants->create($organizationName, self::LAUNCH_JURISDICTION);
            $this->owners->provision($tenant->id, $userId, 'onboarding:'.$idempotencyKey);
            $draft->fill([
                'tenant_id' => $tenant->id,
                'status' => 'completed',
                'completed_at' => now(),
                'completion_idempotency_key' => $idempotencyKey,
            ]);
            $draft->save();

            return $this->view($draft->refresh());
        });
    }

    public function completePosLocations(string $userId, array $setup, int $expectedRevision, string $idempotencyKey): OnboardingDraftView
    {
        $idempotencyKey = trim($idempotencyKey);
        if ($idempotencyKey === '' || strlen($idempotencyKey) > 128) {
            throw new DomainException('A bounded idempotency key is required.');
        }

        foreach (['companyName', 'branchCode', 'branchName', 'warehouseCode', 'warehouseName', 'registerCode', 'registerName'] as $field) {
            if (! is_string($setup[$field] ?? null) || trim($setup[$field]) === '') {
                throw new DomainException('All first-location fields are required.');
            }
        }

        return DB::transaction(function () use ($userId, $setup, $expectedRevision, $idempotencyKey): OnboardingDraftView {
            $draft = OnboardingDraft::query()->where('user_id', $userId)->lockForUpdate()->first();
            if (! $draft instanceof OnboardingDraft || $draft->tenant_id === null) {
                throw new DomainException('Create the POS workspace before adding its first location.');
            }
            if ($draft->register_id !== null) {
                return $this->view($draft);
            }
            if ($draft->revision !== $expectedRevision) {
                throw new DomainException('The onboarding draft changed. Refresh before saving locations.');
            }

            $ids = $this->tenantScope->runFor((string) $draft->tenant_id, function () use ($setup): array {
                $company = $this->locations->createCompany(trim($setup['companyName']));
                $branch = $this->locations->createBranch((string) $company->getKey(), trim($setup['branchCode']), trim($setup['branchName']));
                $warehouse = $this->locations->createWarehouse((string) $branch->getKey(), trim($setup['warehouseCode']), trim($setup['warehouseName']));
                $register = $this->registers->createRegister((string) $branch->getKey(), trim($setup['registerCode']), trim($setup['registerName']));

                return [
                    'company_id' => (string) $company->getKey(),
                    'branch_id' => (string) $branch->getKey(),
                    'warehouse_id' => (string) $warehouse->getKey(),
                    'register_id' => (string) $register->getKey(),
                ];
            });

            $draft->fill($ids + [
                'revision' => $draft->revision + 1,
                'status' => 'ready',
                'location_completion_idempotency_key' => $idempotencyKey,
            ])->save();

            return $this->view($draft->refresh());
        });
    }

    private function view(OnboardingDraft $draft): OnboardingDraftView
    {
        $channel = $draft->channel_selection === null ? null : ChannelSelection::from($draft->channel_selection);
        [$automated, $approvals] = $this->plan($channel);

        return new OnboardingDraftView(
            (string) $draft->getKey(),
            $channel,
            $draft->industry_profile,
            $draft->answers,
            (int) $draft->revision,
            (string) $draft->status,
            $this->nextStep($channel, $draft->industry_profile, $draft->answers, $draft->tenant_id, $draft->register_id),
            $automated,
            $approvals,
            $draft->tenant_id === null ? null : (string) $draft->tenant_id,
            $draft->completed_at?->format(DATE_ATOM),
            $draft->company_id === null ? null : (string) $draft->company_id,
            $draft->branch_id === null ? null : (string) $draft->branch_id,
            $draft->warehouse_id === null ? null : (string) $draft->warehouse_id,
            $draft->register_id === null ? null : (string) $draft->register_id,
        );
    }

    private function channel(mixed $value): ?string
    {
        if ($value === null || $value === '') {
            return null;
        }

        return ChannelSelection::from((string) $value)->value;
    }

    private function industry(mixed $value): ?string
    {
        if ($value === null || $value === '') {
            return null;
        }

        $value = strtolower(trim((string) $value));
        if (! in_array($value, ['grocery', 'supermarket', 'bakery', 'restaurant', 'apparel', 'salon', 'wholesale'], true)) {
            throw new DomainException('That industry profile is not available yet.');
        }

        return $value;
    }

    /** @return array{0: list<string>, 1: list<string>} */
    private function plan(?ChannelSelection $channel): array
    {
        $automated = ['Create a resumable onboarding draft', 'Apply the selected industry blueprint', 'Prepare tenant-safe role and navigation defaults'];
        $approvals = ['Confirm organization and legal/display details', 'Review permissions and operating policies'];

        if ($channel === null) {
            return [$automated, $approvals];
        }

        if ($channel->includesWeb()) {
            $automated[] = 'Prepare a web storefront client and publication checklist';
            $approvals[] = 'Approve domain, payment provider, and storefront publication';
        }

        if ($channel->includesMobile()) {
            $automated[] = 'Prepare a mobile client and build configuration';
            $approvals[] = 'Approve app signing, store submission, and release';
        }

        return [$automated, $approvals];
    }

    /** @param array<string, mixed> $answers */
    private function nextStep(?ChannelSelection $channel, ?string $industry, array $answers, mixed $tenantId, mixed $registerId): string
    {
        if ($channel === null) {
            return 'channel';
        }
        if ($industry === null) {
            return 'industry';
        }
        if (! isset($answers['organizationName']) || trim((string) $answers['organizationName']) === '') {
            return 'organization';
        }

        if ($channel === ChannelSelection::Pos && ($tenantId === null || $registerId === null)) {
            return 'pos_locations';
        }

        if ($channel === ChannelSelection::Pos) {
            return 'ready';
        }

        return match ($channel) {
            ChannelSelection::Web => $this->registrationExists($tenantId, 'web') ? 'ready' : 'web_storefront',
            ChannelSelection::Mobile => $this->registrationExists($tenantId, 'mobile') ? 'ready' : 'mobile_app',
            ChannelSelection::WebMobile => ! $this->registrationExists($tenantId, 'web')
                ? 'web_storefront'
                : (! $this->registrationExists($tenantId, 'mobile') ? 'mobile_app' : 'ready'),
        };
    }

    private function registrationExists(mixed $tenantId, string $channel): bool
    {
        if (! is_string($tenantId) || $tenantId === '') {
            return false;
        }

        return $this->tenantScope->runFor($tenantId, fn (): bool => $this->channels->registrations()->contains(
            static fn (ChannelRegistrationView $registration): bool => $registration->channel === $channel,
        ));
    }
}
