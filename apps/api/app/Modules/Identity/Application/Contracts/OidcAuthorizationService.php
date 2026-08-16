<?php

declare(strict_types=1);

namespace App\Modules\Identity\Application\Contracts;

use App\Modules\Identity\Application\OidcAuthorizationRequest;

interface OidcAuthorizationService
{
    public function begin(): OidcAuthorizationRequest;
}
