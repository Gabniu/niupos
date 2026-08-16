<?php

declare(strict_types=1);

namespace App\Modules\Identity\Application;

final readonly class OidcTokenResponse
{
    /** @param array<string, mixed> $raw */
    public function __construct(
        public string $accessToken,
        public string $tokenType,
        public int $expiresIn,
        public string $idToken,
        public ?string $refreshToken,
        public array $raw = [],
    ) {}
}
