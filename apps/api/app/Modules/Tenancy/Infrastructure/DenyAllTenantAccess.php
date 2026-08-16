<?php

declare(strict_types=1);

namespace App\Modules\Tenancy\Infrastructure;

use App\Modules\Tenancy\Application\Contracts\TenantAccessAuthorizer;
use Illuminate\Http\Request;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;

final class DenyAllTenantAccess implements TenantAccessAuthorizer
{
    public function assertCanAccess(Request $request, string $tenantId): void
    {
        throw new AccessDeniedHttpException(
            'Tenant access remains denied until an authenticated IAM adapter authorizes membership.',
        );
    }
}
