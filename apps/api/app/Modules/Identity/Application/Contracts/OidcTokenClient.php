<?php

declare(strict_types=1);

namespace App\Modules\Identity\Application\Contracts;

use App\Modules\Identity\Application\OidcTokenResponse;

interface OidcTokenClient
{
    public function exchange(string $code, string $verifier, string $redirectUri): OidcTokenResponse;
}
