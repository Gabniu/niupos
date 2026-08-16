<?php

declare(strict_types=1);

namespace App\Modules\Identity\Application\Contracts;

use App\Modules\Identity\Application\FederatedIdentity;
use App\Modules\Identity\Application\OidcTokenResponse;

interface OidcIdentityVerifier
{
    public function verify(OidcTokenResponse $tokens, string $expectedNonce): FederatedIdentity;
}
