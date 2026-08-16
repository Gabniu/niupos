<?php

declare(strict_types=1);

namespace App\Modules\Identity\Application\Contracts;

use App\Modules\Identity\Application\OidcProviderMetadata;

interface OidcDiscoveryClient
{
    public function metadata(): OidcProviderMetadata;
}
