<?php

declare(strict_types=1);

namespace App\Modules\Channels\Application;

final readonly class ChannelRegistrationView
{
    /** @param array<string, mixed> $configuration @param list<string> $redirectUris */
    public function __construct(
        public string $id,
        public string $channel,
        public string $audience,
        public string $environment,
        public string $displayName,
        public string $clientId,
        public string $status,
        public array $configuration,
        public array $redirectUris,
    ) {}

    /** @return array<string, mixed> */
    public function toArray(): array
    {
        return [
            'id' => $this->id,
            'channel' => $this->channel,
            'audience' => $this->audience,
            'environment' => $this->environment,
            'displayName' => $this->displayName,
            'clientId' => $this->clientId,
            'status' => $this->status,
            'configuration' => $this->configuration,
            'redirectUris' => $this->redirectUris,
            'secretAvailable' => false,
        ];
    }
}
