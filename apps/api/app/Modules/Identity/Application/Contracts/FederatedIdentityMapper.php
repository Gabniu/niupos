<?php

declare(strict_types=1);

namespace App\Modules\Identity\Application\Contracts;

use App\Modules\Identity\Application\FederatedIdentity;
use App\Modules\Identity\Application\FederatedIdentityAdmission;
use App\Modules\Identity\Domain\User;

interface FederatedIdentityMapper
{
    /** Resolve only an exact existing provider subject; never email-link/create. */
    public function resolve(FederatedIdentity $identity): ?User;

    /**
     * Resolve only an already-linked provider subject and admit it to an
     * active local tenant membership. No email matching or user creation is
     * performed by this boundary.
     */
    public function admit(FederatedIdentity $identity, string $tenantId): ?FederatedIdentityAdmission;
}
