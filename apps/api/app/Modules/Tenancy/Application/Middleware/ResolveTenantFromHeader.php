<?php

declare(strict_types=1);

namespace App\Modules\Tenancy\Application\Middleware;

use App\Modules\Tenancy\Application\Contracts\TenantAccessAuthorizer;
use App\Modules\Tenancy\Application\TenantScope;
use App\Modules\Tenancy\Domain\TenantId;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;

final readonly class ResolveTenantFromHeader
{
    public function __construct(
        private TenantScope $scope,
        private TenantAccessAuthorizer $authorizer,
    ) {}

    public function handle(Request $request, Closure $next): Response
    {
        $header = $request->header('X-Tenant-Id');

        if (! is_string($header) || $header === '') {
            throw new BadRequestHttpException('X-Tenant-Id is required.');
        }

        try {
            $tenantId = TenantId::fromString($header);
        } catch (\InvalidArgumentException $exception) {
            throw new BadRequestHttpException('X-Tenant-Id must be a valid UUID.', $exception);
        }

        $this->authorizer->assertCanAccess($request, (string) $tenantId);

        return $this->scope->run($tenantId, fn (): Response => $next($request));
    }
}
