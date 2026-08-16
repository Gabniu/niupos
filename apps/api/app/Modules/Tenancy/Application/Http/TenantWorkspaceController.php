<?php

declare(strict_types=1);

namespace App\Modules\Tenancy\Application\Http;

use App\Modules\Tenancy\Application\TenantContext;
use App\Modules\Tenancy\Domain\Branch;
use App\Modules\Tenancy\Domain\Company;
use App\Modules\Tenancy\Domain\Tenant;
use App\Modules\Tenancy\Domain\Warehouse;
use Illuminate\Http\JsonResponse;

final readonly class TenantWorkspaceController
{
    public function __construct(private TenantContext $context) {}

    public function overview(): JsonResponse
    {
        $tenantId = (string) $this->context->id();
        $tenant = Tenant::query()->findOrFail($tenantId);

        $counts = [
            'companies' => Company::query()->where('tenant_id', $tenantId)->where('status', 'active')->count(),
            'branches' => Branch::query()->where('tenant_id', $tenantId)->where('status', 'active')->count(),
            'warehouses' => Warehouse::query()->where('tenant_id', $tenantId)->where('status', 'active')->count(),
        ];

        return new JsonResponse(['data' => [
            'tenantName' => (string) $tenant->name,
            'metrics' => [
                ['label' => 'Active companies', 'value' => (string) $counts['companies']],
                ['label' => 'Active branches', 'value' => (string) $counts['branches']],
                ['label' => 'Active warehouses', 'value' => (string) $counts['warehouses']],
            ],
            'activity' => [],
        ]]);
    }
}
