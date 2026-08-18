<?php

declare(strict_types=1);

namespace App\Modules\Reports\Application\Http;

use App\Modules\Tenancy\Application\TenantContext;
use App\Modules\Tenancy\Application\Contracts\WorkspacePreferencesReader;
use Carbon\CarbonImmutable;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

final readonly class ReportsController
{
    public function __construct(private TenantContext $context, private WorkspacePreferencesReader $preferences) {}

    public function summary(Request $request): JsonResponse
    {
        $timezone = $this->preferences->reportingTimezone();
        $now = CarbonImmutable::now($timezone);
        $from = $this->date($request->query('from'), $now->startOfMonth()->utc(), $timezone, false);
        $to = $this->date($request->query('to'), $now->endOfDay()->utc(), $timezone, true);
        if ($from->greaterThan($to) || $from->diffInDays($to) > 366) {
            return new JsonResponse(['error' => ['code' => 'invalid_period', 'message' => 'The report period is invalid.']], 422);
        }
        $tenantId = (string) $this->context->id();
        $sales = DB::table('sales')->where('tenant_id', $tenantId)->where('status', 'finalized')->whereBetween('finalized_at', [$from, $to]);
        $totals = (clone $sales)->select('currency_code')->selectRaw('COUNT(*) as sales_count')->selectRaw('SUM(gross_minor) as gross_minor')->selectRaw('SUM(net_minor) as net_minor')->selectRaw('SUM(tax_minor) as tax_minor')->groupBy('currency_code')->orderBy('currency_code')->get()->map(static fn (object $row): array => [
            'currencyCode' => (string) $row->currency_code,
            'salesCount' => (int) $row->sales_count,
            'grossMinor' => (int) $row->gross_minor,
            'netMinor' => (int) $row->net_minor,
            'taxMinor' => (int) $row->tax_minor,
        ])->values()->all();
        $topProducts = DB::table('sale_lines as lines')
            ->join('sales', function ($join): void { $join->on('sales.id', '=', 'lines.sale_id')->on('sales.tenant_id', '=', 'lines.tenant_id'); })
            ->join('catalogue_product_variants as variants', function ($join): void { $join->on('variants.id', '=', 'lines.variant_id')->on('variants.tenant_id', '=', 'lines.tenant_id'); })
            ->join('catalogue_products as products', function ($join): void { $join->on('products.id', '=', 'variants.product_id')->on('products.tenant_id', '=', 'variants.tenant_id'); })
            ->where('lines.tenant_id', $tenantId)->where('sales.status', 'finalized')->whereBetween('sales.finalized_at', [$from, $to])
            ->select('sales.currency_code', 'products.name as product_name', 'variants.name as variant_name')->selectRaw('SUM(lines.quantity) as quantity')->selectRaw('SUM(lines.gross_minor) as gross_minor')->groupBy('sales.currency_code', 'products.name', 'variants.name')->orderByDesc('gross_minor')->limit(10)->get()->map(static fn (object $row): array => [
                'currencyCode' => (string) $row->currency_code,
                'productName' => (string) $row->product_name,
                'variantName' => (string) $row->variant_name,
                'quantity' => (int) $row->quantity,
                'grossMinor' => (int) $row->gross_minor,
            ])->values()->all();

        return new JsonResponse(['data' => ['period' => ['from' => $from->toIso8601String(), 'to' => $to->toIso8601String(), 'timezone' => $timezone], 'totals' => $totals, 'topProducts' => $topProducts]]);
    }

    private function date(mixed $value, CarbonImmutable $fallback, string $timezone, bool $endOfDay): CarbonImmutable
    {
        $text = trim((string) $value);
        if ($text === '') return $fallback;
        try {
            $date = CarbonImmutable::parse($text, $timezone);
            if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $text) === 1) {
                $date = $endOfDay ? $date->endOfDay() : $date->startOfDay();
            }

            return $date->utc();
        } catch (\Throwable) {
            return CarbonImmutable::createFromTimestamp(0);
        }
    }
}
