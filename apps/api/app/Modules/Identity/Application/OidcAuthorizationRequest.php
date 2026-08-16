<?php

declare(strict_types=1);

namespace App\Modules\Identity\Application;

final readonly class OidcAuthorizationRequest
{
    public function __construct(
        public string $authorizationUrl,
        public string $state,
        public string $expiresAt,
    ) {}
}
