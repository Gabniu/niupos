<?php

declare(strict_types=1);

namespace App\Modules\Tenancy\Application\Contracts;

use Illuminate\Http\Request;

interface TenantAccessAuthorizer
{
    public function assertCanAccess(Request $request, string $tenantId): void;
}
