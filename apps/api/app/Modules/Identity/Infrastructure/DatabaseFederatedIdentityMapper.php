<?php

declare(strict_types=1);

namespace App\Modules\Identity\Infrastructure;

use App\Modules\Identity\Application\Contracts\FederatedIdentityMapper;
use App\Modules\Identity\Application\FederatedIdentity;
use App\Modules\Identity\Application\FederatedIdentityAdmission;
use App\Modules\Identity\Domain\TenantMembership;
use App\Modules\Identity\Domain\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

final class DatabaseFederatedIdentityMapper implements FederatedIdentityMapper
{
    public function resolve(FederatedIdentity $identity): ?User
    {
        if ($identity->issuer === '' || $identity->subject === '') {
            return null;
        }

        $user = User::query()
            ->where('identity_issuer', $identity->issuer)
            ->where('identity_subject', $identity->subject)
            ->first();

        return $user instanceof User ? $user : null;
    }

    public function admit(FederatedIdentity $identity, string $tenantId): ?FederatedIdentityAdmission
    {
        if (! Str::isUuid($tenantId) || $identity->issuer === '' || $identity->subject === '') {
            return null;
        }

        $tenantIsActive = DB::table('tenants')
            ->where('id', $tenantId)
            ->where('status', 'active')
            ->exists();

        if (! $tenantIsActive) {
            return null;
        }

        $user = $this->resolve($identity);

        if (! $user instanceof User) {
            return null;
        }

        $membership = TenantMembership::query()
            ->where('tenant_id', $tenantId)
            ->where('user_id', $user->getKey())
            ->where('status', 'active')
            ->first();

        return $membership instanceof TenantMembership
            ? new FederatedIdentityAdmission($user, $membership, $tenantId)
            : null;
    }
}
