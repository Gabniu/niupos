<?php

declare(strict_types=1);

namespace App\Modules\Tenancy\Application;

use App\Modules\Tenancy\Domain\TenantId;
use LogicException;

final class TenantContext
{
    private ?TenantId $tenantId = null;

    public function set(TenantId $tenantId): void
    {
        if ($this->tenantId !== null && ! $this->tenantId->equals($tenantId)) {
            throw new LogicException('Tenant context cannot change during one execution scope.');
        }

        $this->tenantId = $tenantId;
    }

    public function id(): TenantId
    {
        return $this->tenantId ?? throw new LogicException('Tenant context has not been established.');
    }

    public function hasTenant(): bool
    {
        return $this->tenantId !== null;
    }

    public function clear(): void
    {
        $this->tenantId = null;
    }
}
