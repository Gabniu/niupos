<?php

declare(strict_types=1);

namespace App\Modules\Identity\Application\Contracts;

use App\Modules\Identity\Application\FederatedIdentity;

interface FederatedIdentityResolver
{
    public function resolve(string $bearerToken): ?FederatedIdentity;
}
