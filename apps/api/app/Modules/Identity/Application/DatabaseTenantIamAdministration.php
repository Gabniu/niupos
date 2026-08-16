<?php

declare(strict_types=1);

namespace App\Modules\Identity\Application;

use App\Modules\Audit\Application\Contracts\SecurityAuditRecorder;
use App\Modules\Audit\Application\SecurityAuditEvent;
use App\Modules\Identity\Application\Contracts\PermissionAuthorizer;
use App\Modules\Identity\Application\Contracts\TenantIamAdministration;
use App\Modules\Identity\Domain\MembershipStatus;
use App\Modules\Identity\Domain\PermissionKey;
use App\Modules\Identity\Domain\Role;
use App\Modules\Identity\Domain\TenantMembership;
use App\Modules\Identity\Domain\User;
use App\Modules\Tenancy\Application\TenantContext;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;
use DomainException;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;

final readonly class DatabaseTenantIamAdministration implements TenantIamAdministration
{
    public function __construct(
        private TenantContext $tenantContext,
        private PermissionAuthorizer $permissions,
        private SecurityAuditRecorder $audit,
    ) {}

    public function createRole(User $actor, string $name, ?string $description = null): Role
    {
        $this->assertAllowed($actor, 'iam.roles.manage');
        $normalizedName = mb_strtolower(trim($name));
        if (preg_match('/^[a-z][a-z0-9_-]{1,63}$/', $normalizedName) !== 1) {
            throw new InvalidArgumentException('Role names must be 2-64 lowercase letters, numbers, underscores, or hyphens.');
        }
        $tenantId = (string) $this->tenantContext->id();

        return DB::transaction(function () use ($actor, $normalizedName, $description, $tenantId): Role {
            $role = Role::query()->create([
                'tenant_id' => $tenantId,
                'name' => $normalizedName,
                'description' => $description,
            ]);
            $this->record($tenantId, $actor, 'identity.role.created', [
                'role_id' => (string) $role->getKey(),
                'role_name' => $normalizedName,
            ]);

            return $role;
        });
    }

    public function replaceRolePermissions(User $actor, string $roleId, array $permissionKeys): void
    {
        $this->assertAllowed($actor, 'iam.roles.manage');
        $tenantId = (string) $this->tenantContext->id();
        $role = Role::query()->where('tenant_id', $tenantId)->findOrFail($roleId);
        $keys = array_values(array_unique(array_map(
            static fn (string $key): string => (string) new PermissionKey($key),
            $permissionKeys,
        )));
        if (DB::table('permissions')->whereIn('id', $keys)->count() !== count($keys)) {
            throw new InvalidArgumentException('Every assigned permission must exist in the catalogue.');
        }

        DB::transaction(function () use ($actor, $tenantId, $role, $keys): void {
            DB::table('role_permissions')->where('tenant_id', $tenantId)->where('role_id', $role->getKey())->delete();
            if ($keys !== []) {
                DB::table('role_permissions')->insert(array_map(fn (string $key): array => [
                    'tenant_id' => $tenantId,
                    'role_id' => $role->getKey(),
                    'permission_id' => $key,
                ], $keys));
            }
            sort($keys);
            $this->record($tenantId, $actor, 'identity.role_permissions.replaced', [
                'role_id' => (string) $role->getKey(),
                'permission_count' => count($keys),
                'permission_set_hash' => hash('sha256', implode("\n", $keys)),
            ]);
        });
    }

    public function assignMembership(User $actor, string $userId, string $roleId, MembershipStatus $status): TenantMembership
    {
        $this->assertAllowed($actor, 'iam.memberships.manage');
        $tenantId = (string) $this->tenantContext->id();
        Role::query()->where('tenant_id', $tenantId)->findOrFail($roleId);
        User::query()->findOrFail($userId);
        $existing = TenantMembership::query()->where('tenant_id', $tenantId)->where('user_id', $userId)->first();
        if ($existing?->is_owner && ($status !== MembershipStatus::Active || $existing->role_id !== $roleId)) {
            $ownerCount = TenantMembership::query()->where('tenant_id', $tenantId)
                ->where('is_owner', true)->where('status', MembershipStatus::Active->value)->count();
            if ($ownerCount <= 1) {
                throw new DomainException('The last active tenant owner cannot be revoked or reassigned.');
            }
        }

        return DB::transaction(function () use ($actor, $tenantId, $userId, $roleId, $status): TenantMembership {
            $membership = TenantMembership::query()->updateOrCreate(
                ['tenant_id' => $tenantId, 'user_id' => $userId],
                ['role_id' => $roleId, 'status' => $status->value],
            );
            $this->record($tenantId, $actor, 'identity.membership.assigned', [
                'membership_id' => (string) $membership->getKey(),
                'target_user_id' => $userId,
                'role_id' => $roleId,
                'status' => $status->value,
            ]);

            return $membership;
        });
    }

    public function transferOwnership(User $actor, string $targetUserId): void
    {
        $this->assertAllowed($actor, 'iam.memberships.manage');
        $tenantId = (string) $this->tenantContext->id();
        if ((string) $actor->getKey() === $targetUserId) {
            throw new DomainException('Ownership transfer requires a different target user.');
        }

        DB::transaction(function () use ($actor, $targetUserId, $tenantId): void {
            $memberships = TenantMembership::query()->where('tenant_id', $tenantId)
                ->whereIn('user_id', [(string) $actor->getKey(), $targetUserId])
                ->orderBy('user_id')->lockForUpdate()->get()->keyBy('user_id');
            $source = $memberships->get((string) $actor->getKey());
            $target = $memberships->get($targetUserId);
            if (! $source instanceof TenantMembership || ! $source->is_owner || $source->status !== MembershipStatus::Active->value) {
                throw new AccessDeniedHttpException('Only an active tenant owner may transfer ownership.');
            }
            if (! $target instanceof TenantMembership || $target->status !== MembershipStatus::Active->value) {
                throw new DomainException('The target must have an active membership in the tenant.');
            }

            $target->forceFill(['is_owner' => true])->save();
            $source->forceFill(['is_owner' => false])->save();
            $this->record($tenantId, $actor, 'identity.owner.transferred', [
                'from_user_id' => (string) $actor->getKey(),
                'to_user_id' => $targetUserId,
                'from_membership_id' => (string) $source->getKey(),
                'to_membership_id' => (string) $target->getKey(),
            ]);
        });
    }

    private function assertAllowed(User $actor, string $permission): void
    {
        if (! $this->permissions->allows($actor, new PermissionKey($permission))) {
            throw new AccessDeniedHttpException('The required tenant permission is missing.');
        }
    }

    /** @param array<string, scalar|null> $metadata */
    private function record(string $tenantId, User $actor, string $type, array $metadata): void
    {
        $this->audit->record(new SecurityAuditEvent(
            $type,
            (string) $actor->getKey(),
            $metadata,
            $tenantId,
        ));
    }
}
