<?php

declare(strict_types=1);

namespace App\Modules\Identity\Application\Contracts;

use App\Modules\Identity\Application\OwnerMembershipRecord;

interface OwnerMembershipProvisioner
{
    public function provision(string $tenantId, string $userId, string $operatorReference): OwnerMembershipRecord;
}
