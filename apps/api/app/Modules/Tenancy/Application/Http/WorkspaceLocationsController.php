<?php

declare(strict_types=1);

namespace App\Modules\Tenancy\Application\Http;

use App\Modules\Tenancy\Application\TenantContext;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;

final readonly class WorkspaceLocationsController
{
    public function __construct(private TenantContext $context) {}

    public function index(): JsonResponse
    {
        $tenantId = (string) $this->context->id();
        $branches = DB::table('branches')
            ->where('tenant_id', $tenantId)
            ->where('status', 'active')
            ->orderBy('name')
            ->get(['id', 'company_id', 'code', 'name']);
        $branchIds = $branches->pluck('id')->map(static fn (mixed $id): string => (string) $id)->all();

        if ($branchIds === []) {
            return new JsonResponse(['data' => []]);
        }

        $warehouses = DB::table('warehouses')
            ->where('tenant_id', $tenantId)
            ->whereIn('branch_id', $branchIds)
            ->where('status', 'active')
            ->orderBy('name')
            ->get(['id', 'branch_id', 'code', 'name'])
            ->groupBy('branch_id');
        $registers = DB::table('registers')
            ->where('tenant_id', $tenantId)
            ->whereIn('branch_id', $branchIds)
            ->where('status', 'active')
            ->orderBy('name')
            ->get(['id', 'branch_id', 'code', 'name'])
            ->groupBy('branch_id');

        $data = $branches->map(static function (object $branch) use ($warehouses, $registers): array {
            $branchId = (string) $branch->id;

            return [
                'id' => $branchId,
                'companyId' => (string) $branch->company_id,
                'code' => (string) $branch->code,
                'name' => (string) $branch->name,
                'warehouses' => $warehouses->get($branch->id, collect())->map(static fn (object $warehouse): array => [
                    'id' => (string) $warehouse->id,
                    'code' => (string) $warehouse->code,
                    'name' => (string) $warehouse->name,
                ])->values()->all(),
                'registers' => $registers->get($branch->id, collect())->map(static fn (object $register): array => [
                    'id' => (string) $register->id,
                    'code' => (string) $register->code,
                    'name' => (string) $register->name,
                ])->values()->all(),
            ];
        })->values()->all();

        return new JsonResponse(['data' => $data]);
    }
}
