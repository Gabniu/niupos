<?php

declare(strict_types=1);

namespace App\Modules\Identity\Application;

use App\Modules\Identity\Application\Contracts\OwnerMembershipProvisioner;
use App\Modules\Identity\Application\Contracts\TenantOwnerBootstrap;
use App\Modules\Identity\Domain\User;
use DomainException;

final readonly class DatabaseOwnerMembershipProvisioner implements OwnerMembershipProvisioner
{
    public function __construct(private TenantOwnerBootstrap $bootstrap) {}

    public function provision(string $tenantId, string $userId, string $operatorReference): OwnerMembershipRecord
    {
        $owner = User::query()->find($userId);
        if (! $owner instanceof User) {
            throw new DomainException('The authenticated owner could not be found.');
        }

        $membership = $this->bootstrap->bootstrap($tenantId, $owner, $operatorReference);

        return new OwnerMembershipRecord((string) $membership->getKey(), $tenantId, $userId);
    }
}
