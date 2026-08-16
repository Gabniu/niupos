<?php

declare(strict_types=1);

namespace App\Modules\Channels\Application\Contracts;

use App\Modules\Channels\Application\ChannelRegistrationView;
use Illuminate\Support\Collection;

interface ChannelRegistrationManager
{
    /** @return Collection<int, ChannelRegistrationView> */
    public function registrations(): Collection;

    /** @param array<string, mixed> $configuration @param list<string> $redirectUris */
    public function create(string $channel, string $displayName, string $environment, array $configuration, array $redirectUris, string $idempotencyKey): ChannelRegistrationView;

    public function requestApproval(string $registrationId): ChannelRegistrationView;
}
