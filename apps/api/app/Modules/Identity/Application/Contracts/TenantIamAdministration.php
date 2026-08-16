<?php

declare(strict_types=1);

namespace App\Modules\Identity\Application\Contracts;

use App\Modules\Identity\Domain\MembershipStatus;
use App\Modules\Identity\Domain\Role;
use App\Modules\Identity\Domain\TenantMembership;
use App\Modules\Identity\Domain\User;

interface TenantIamAdministration
{
    public function createRole(User $actor, string $name, ?string $description = null): Role;

    /** @param list<string> $permissionKeys */
    public function replaceRolePermissions(User $actor, string $roleId, array $permissionKeys): void;

    public function assignMembership(
        User $actor,
        string $userId,
        string $roleId,
        MembershipStatus $status,
    ): TenantMembership;

    public function transferOwnership(User $actor, string $targetUserId): void;
}
