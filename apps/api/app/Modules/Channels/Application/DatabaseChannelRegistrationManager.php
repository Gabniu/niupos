<?php

declare(strict_types=1);

namespace App\Modules\Channels\Application;

use App\Modules\Channels\Application\Contracts\ChannelRegistrationManager;
use App\Modules\Channels\Domain\ChannelRegistration;
use App\Modules\Channels\Domain\ChannelRegistrationStatus;
use App\Modules\Channels\Domain\CommerceChannel;
use App\Modules\Tenancy\Application\TenantContext;
use DomainException;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;

final readonly class DatabaseChannelRegistrationManager implements ChannelRegistrationManager
{
    public function __construct(private TenantContext $context) {}

    public function registrations(): Collection
    {
        return ChannelRegistration::query()
            ->where('tenant_id', (string) $this->context->id())
            ->orderBy('channel')
            ->orderBy('environment')
            ->get()
            ->map(fn (ChannelRegistration $registration): ChannelRegistrationView => $this->view($registration));
    }

    public function create(string $channel, string $displayName, string $environment, array $configuration, array $redirectUris, string $idempotencyKey): ChannelRegistrationView
    {
        $idempotencyKey = trim($idempotencyKey);
        if ($idempotencyKey === '' || strlen($idempotencyKey) > 128) {
            throw new DomainException('A bounded idempotency key is required.');
        }
        $channel = CommerceChannel::tryFrom(strtolower(trim($channel)));
        $displayName = trim($displayName);
        $environment = strtolower(trim($environment));
        if ($channel === null || $displayName === '' || strlen($displayName) > 160) {
            throw new DomainException('A supported channel and display name are required.');
        }
        if (! in_array($environment, ['development', 'staging', 'production'], true)) {
            throw new DomainException('The channel environment is invalid.');
        }
        if (array_intersect(array_keys($configuration), ['secret', 'clientSecret', 'privateKey', 'accessToken']) !== []) {
            throw new DomainException('Secrets must be configured through the server-side secret manager.');
        }
        $redirectUris = array_values(array_filter(array_map(static fn (mixed $uri): string => trim((string) $uri), $redirectUris), static fn (string $uri): bool => $uri !== ''));
        foreach ($redirectUris as $uri) {
            if (! filter_var($uri, FILTER_VALIDATE_URL)) {
                throw new DomainException('Every redirect URI must be a valid URL.');
            }
        }

        $tenantId = (string) $this->context->id();
        $fingerprint = hash('sha256', json_encode([
            'channel' => $channel->value,
            'displayName' => $displayName,
            'environment' => $environment,
            'configuration' => $configuration,
            'redirectUris' => $redirectUris,
        ], JSON_THROW_ON_ERROR));
        $replay = ChannelRegistration::query()->where('tenant_id', $tenantId)->where('idempotency_key', $idempotencyKey)->first();
        if ($replay instanceof ChannelRegistration) {
            if ($replay->idempotency_fingerprint !== $fingerprint) {
                throw new DomainException('The idempotency key was already used for different channel data.');
            }

            return $this->view($replay);
        }
        $existing = ChannelRegistration::query()->where('tenant_id', $tenantId)->where('channel', $channel->value)->where('environment', $environment)->exists();
        if ($existing) {
            throw new DomainException('A registration already exists for this channel and environment.');
        }

        $registration = ChannelRegistration::query()->create([
            'tenant_id' => $tenantId,
            'channel' => $channel->value,
            'audience' => 'customer',
            'environment' => $environment,
            'display_name' => $displayName,
            'client_id' => (string) Str::uuid(),
            'status' => ChannelRegistrationStatus::Draft->value,
            'configuration' => $configuration,
            'redirect_uris' => $redirectUris,
            'idempotency_key' => $idempotencyKey,
            'idempotency_fingerprint' => $fingerprint,
        ]);

        return $this->view($registration);
    }

    public function requestApproval(string $registrationId): ChannelRegistrationView
    {
        $registration = ChannelRegistration::query()->where('tenant_id', (string) $this->context->id())->lockForUpdate()->findOrFail($registrationId);
        if ($registration->status !== ChannelRegistrationStatus::Draft->value) {
            throw new DomainException('Only a draft channel registration can be submitted for approval.');
        }
        $registration->forceFill(['status' => ChannelRegistrationStatus::ApprovalRequired->value])->save();

        return $this->view($registration->refresh());
    }

    private function view(ChannelRegistration $registration): ChannelRegistrationView
    {
        return new ChannelRegistrationView(
            (string) $registration->getKey(),
            (string) $registration->channel,
            (string) $registration->audience,
            (string) $registration->environment,
            (string) $registration->display_name,
            (string) $registration->client_id,
            (string) $registration->status,
            $registration->configuration ?? [],
            $registration->redirect_uris ?? [],
        );
    }
}
