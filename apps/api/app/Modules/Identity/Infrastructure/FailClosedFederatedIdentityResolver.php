<?php

declare(strict_types=1);

namespace App\Modules\Identity\Infrastructure;

use App\Modules\Identity\Application\Contracts\FederatedIdentityResolver;
use App\Modules\Identity\Application\FederatedIdentity;

final class FailClosedFederatedIdentityResolver implements FederatedIdentityResolver
{
    public function resolve(string $bearerToken): ?FederatedIdentity
    {
        // The OIDC/JWKS verifier is deliberately not enabled until its issuer,
        // audience, algorithm allow-list, cache, and revocation contract are
        // configured by the federation migration.
        return null;
    }
}
