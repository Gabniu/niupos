<?php

declare(strict_types=1);

namespace App\Modules\Identity\Infrastructure;

use App\Modules\Identity\Domain\TenantMembership;
use App\Modules\Tenancy\Application\Contracts\TenantAccessAuthorizer;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Http\Request;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;

final class DatabaseTenantAccessAuthorizer implements TenantAccessAuthorizer
{
    public function assertCanAccess(Request $request, string $tenantId): void
    {
        $actor = $request->user();

        if (! $actor instanceof Authenticatable) {
            throw new AccessDeniedHttpException('Authentication is required.');
        }

        $authorized = TenantMembership::query()
            ->where('tenant_id', $tenantId)
            ->where('user_id', (string) $actor->getAuthIdentifier())
            ->where('status', 'active')
            ->exists();

        if (! $authorized) {
            throw new AccessDeniedHttpException('Active tenant membership is required.');
        }
    }
}
