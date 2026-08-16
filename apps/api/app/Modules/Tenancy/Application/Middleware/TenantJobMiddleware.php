<?php

declare(strict_types=1);

namespace App\Modules\Tenancy\Application\Middleware;

use App\Modules\Tenancy\Application\Contracts\TenantAwareJob;
use App\Modules\Tenancy\Application\TenantScope;
use App\Modules\Tenancy\Domain\TenantId;
use Closure;
use LogicException;

final readonly class TenantJobMiddleware
{
    public function __construct(private TenantScope $scope) {}

    public function handle(object $job, Closure $next): mixed
    {
        if (! $job instanceof TenantAwareJob) {
            throw new LogicException('Tenant job middleware requires a TenantAwareJob.');
        }

        return $this->scope->run(
            TenantId::fromString($job->tenantId()),
            fn (): mixed => $next($job),
        );
    }
}
