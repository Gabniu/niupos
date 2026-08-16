<?php

declare(strict_types=1);

namespace App\Modules\Identity\Application;

use App\Modules\Identity\Domain\TenantMembership;
use App\Modules\Identity\Domain\User;

final readonly class FederatedIdentityAdmission
{
    public function __construct(
        public User $user,
        public TenantMembership $membership,
        public string $tenantId,
    ) {}
}
