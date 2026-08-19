<?php

declare(strict_types=1);

namespace App\Modules\Reports\Application\Http;

use App\Modules\Fiscal\Application\Contracts\FiscalSubmissionReader;
use App\Modules\Payments\Application\Contracts\PaymentReconciliationReader;
use App\Modules\Tenancy\Application\TenantContext;
use App\Modules\Tenancy\Application\Contracts\WorkspacePreferencesReader;
use Carbon\CarbonImmutable;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

final readonly class ReportsController
{
    public function __construct(private TenantContext $context, private WorkspacePreferencesReader $preferences, private PaymentReconciliationReader $payments, private FiscalSubmissionReader $fiscal) {}

    public function summary(Request $request): JsonResponse
    {
        [$from, $to, $timezone] = $this->bounds($request);
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

    public function reconciliation(Request $request): JsonResponse
    {
        [$from, $to, $timezone] = $this->bounds($request);
        if ($from->greaterThan($to) || $from->diffInDays($to) > 366) {
            return new JsonResponse(['error' => ['code' => 'invalid_period', 'message' => 'The report period is invalid.']], 422);
        }
        $tenantId = (string) $this->context->id();
        $lineTotals = DB::table('sale_lines')
            ->where('tenant_id', $tenantId)
            ->groupBy('sale_id')
            ->select('sale_id')
            ->selectRaw('COALESCE(SUM(gross_minor), 0) as line_gross_minor')
            ->selectRaw('COALESCE(SUM(net_minor), 0) as line_net_minor')
            ->selectRaw('COALESCE(SUM(tax_minor), 0) as line_tax_minor');
        $rows = DB::table('sales')
            ->leftJoinSub($lineTotals, 'line_totals', static fn ($join) => $join->on('line_totals.sale_id', '=', 'sales.id'))
            ->where('sales.tenant_id', $tenantId)
            ->where('sales.status', 'finalized')
            ->whereBetween('sales.finalized_at', [$from, $to])
            ->select('sales.id', 'sales.currency_code', 'sales.gross_minor', 'sales.net_minor', 'sales.tax_minor')
            ->selectRaw('COALESCE(line_totals.line_gross_minor, 0) as line_gross_minor')
            ->selectRaw('COALESCE(line_totals.line_net_minor, 0) as line_net_minor')
            ->selectRaw('COALESCE(line_totals.line_tax_minor, 0) as line_tax_minor')
            ->get();
        $mismatches = $rows->filter(static fn (object $row): bool => (int) $row->gross_minor !== (int) $row->line_gross_minor
            || (int) $row->net_minor !== (int) $row->line_net_minor
            || (int) $row->tax_minor !== (int) $row->line_tax_minor)->take(100)->map(static fn (object $row): array => [
                'saleId' => (string) $row->id,
                'currencyCode' => (string) $row->currency_code,
                'grossMinor' => ['sale' => (int) $row->gross_minor, 'lines' => (int) $row->line_gross_minor],
                'netMinor' => ['sale' => (int) $row->net_minor, 'lines' => (int) $row->line_net_minor],
                'taxMinor' => ['sale' => (int) $row->tax_minor, 'lines' => (int) $row->line_tax_minor],
            ])->values()->all();

        return new JsonResponse(['data' => [
            'period' => ['from' => $from->toIso8601String(), 'to' => $to->toIso8601String(), 'timezone' => $timezone],
            'checkedSales' => $rows->count(),
            'status' => $mismatches === [] ? 'ok' : 'attention',
            'mismatches' => $mismatches,
        ]]);
    }

    public function paymentReconciliation(Request $request): JsonResponse
    {
        [$from, $to, $timezone] = $this->bounds($request);
        if ($from->greaterThan($to) || $from->diffInDays($to) > 366) {
            return new JsonResponse(['error' => ['code' => 'invalid_period', 'message' => 'The report period is invalid.']], 422);
        }
        $sales = DB::table('sales')
            ->where('tenant_id', (string) $this->context->id())
            ->where('status', 'finalized')
            ->whereBetween('finalized_at', [$from, $to])
            ->select('id', 'currency_code', 'gross_minor')
            ->orderBy('id')
            ->get();
        $totals = $this->payments->totalsForSales($sales->pluck('id')->map(static fn (mixed $id): string => (string) $id)->all());
        $allocated = [];
        foreach ($totals as $total) {
            $allocated[$total->saleId.'|'.$total->currencyCode] = $total->allocatedMinor;
        }
        $rows = $sales->map(function (object $sale) use ($allocated): array {
            $gross = (int) $sale->gross_minor;
            $paid = (int) ($allocated[(string) $sale->id.'|'.(string) $sale->currency_code] ?? 0);
            $difference = $paid - $gross;

            return [
                'saleId' => (string) $sale->id,
                'currencyCode' => (string) $sale->currency_code,
                'grossMinor' => $gross,
                'allocatedMinor' => $paid,
                'differenceMinor' => $difference,
                'status' => $difference === 0 ? 'ok' : ($difference < 0 ? 'underpaid' : 'overpaid'),
            ];
        });
        $mismatches = $rows->filter(static fn (array $row): bool => $row['status'] !== 'ok')->take(100)->values()->all();

        return new JsonResponse(['data' => [
            'period' => ['from' => $from->toIso8601String(), 'to' => $to->toIso8601String(), 'timezone' => $timezone],
            'checkedSales' => $rows->count(),
            'fullyPaidSales' => $rows->where('status', 'ok')->count(),
            'status' => $mismatches === [] ? 'ok' : 'attention',
            'mismatches' => $mismatches,
        ]]);
    }

    public function fiscalSubmissions(): JsonResponse
    {
        $summary = $this->fiscal->summary();

        return new JsonResponse(['data' => [
            'counts' => $summary->counts,
            'total' => $summary->total,
            'oldestPendingAt' => $summary->oldestPendingAt,
            'nextRetryAt' => $summary->nextRetryAt,
        ]]);
    }

    /** @return array{0: CarbonImmutable, 1: CarbonImmutable, 2: string} */
    private function bounds(Request $request): array
    {
        $timezone = $this->preferences->reportingTimezone();
        $now = CarbonImmutable::now($timezone);
        $from = $this->date($request->query('from'), $now->startOfMonth()->utc(), $timezone, false);
        $to = $this->date($request->query('to'), $now->endOfDay()->utc(), $timezone, true);
        return [$from, $to, $timezone];
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
