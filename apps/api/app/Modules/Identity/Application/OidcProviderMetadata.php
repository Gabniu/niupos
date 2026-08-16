<?php

declare(strict_types=1);

namespace App\Modules\Identity\Application;

final readonly class OidcProviderMetadata
{
    /** @param list<string> $codeChallengeMethods */
    public function __construct(
        public string $issuer,
        public string $authorizationEndpoint,
        public string $tokenEndpoint,
        public string $jwksUri,
        public string $userinfoEndpoint,
        public array $codeChallengeMethods,
    ) {}
}
