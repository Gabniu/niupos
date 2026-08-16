<?php

declare(strict_types=1);

namespace App\Modules\Tenancy\Application;

use App\Modules\Tenancy\Domain\TenantId;
use Closure;
use Illuminate\Support\Facades\DB;
use Throwable;

final readonly class TenantScope
{
    public function __construct(private TenantContext $context) {}

    /**
     * @template T
     * @param Closure(): T $operation
     * @return T
     * @throws Throwable
     */
    public function run(TenantId $tenantId, Closure $operation): mixed
    {
        $this->context->set($tenantId);

        try {
            if (DB::getDriverName() !== 'pgsql') {
                return $operation();
            }

            return DB::transaction(function () use ($tenantId, $operation): mixed {
                DB::select("select set_config('app.tenant_id', ?, true)", [(string) $tenantId]);

                return $operation();
            });
        } finally {
            $this->context->clear();
        }
    }

    /** @template T @param Closure(): T $operation @return T */
    public function runFor(string $tenantId, Closure $operation): mixed
    {
        return $this->run(TenantId::fromString($tenantId), $operation);
    }
}
