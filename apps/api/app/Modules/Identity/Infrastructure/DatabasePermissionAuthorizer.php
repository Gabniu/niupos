<?php

declare(strict_types=1);

namespace App\Modules\Identity\Infrastructure;

use App\Modules\Identity\Application\Contracts\PermissionAuthorizer;
use App\Modules\Identity\Domain\PermissionKey;
use App\Modules\Identity\Domain\TenantMembership;
use App\Modules\Tenancy\Application\TenantContext;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Support\Facades\DB;

final readonly class DatabasePermissionAuthorizer implements PermissionAuthorizer
{
    public function __construct(private TenantContext $tenantContext) {}

    public function allows(Authenticatable $actor, PermissionKey $permission): bool
    {
        if (! $this->tenantContext->hasTenant()) {
            return false;
        }

        $tenantId = (string) $this->tenantContext->id();

        return TenantMembership::query()
            ->where('tenant_memberships.tenant_id', $tenantId)
            ->where('tenant_memberships.user_id', (string) $actor->getAuthIdentifier())
            ->where('tenant_memberships.status', 'active')
            ->whereExists(function ($query) use ($permission): void {
                $query->select(DB::raw(1))
                    ->from('role_permissions')
                    ->join('permissions', 'permissions.id', '=', 'role_permissions.permission_id')
                    ->whereColumn('role_permissions.role_id', 'tenant_memberships.role_id')
                    ->whereColumn('role_permissions.tenant_id', 'tenant_memberships.tenant_id')
                    ->where('permissions.id', (string) $permission);
            })
            ->exists();
    }
}
