<?php

declare(strict_types=1);

namespace App\Modules\Fiscal\Application;

use App\Modules\Fiscal\Application\Contracts\FiscalSubmissionReader;
use App\Modules\Fiscal\Application\Data\FiscalSubmissionSummary;
use App\Modules\Tenancy\Application\TenantContext;
use Illuminate\Support\Facades\DB;

final readonly class DatabaseFiscalSubmissionReader implements FiscalSubmissionReader
{
    public function __construct(private TenantContext $tenantContext) {}

    public function summary(): FiscalSubmissionSummary
    {
        $query = DB::table('fiscal_invoice_submissions')->where('tenant_id', (string) $this->tenantContext->id());
        $counts = $query->clone()->select('status')->selectRaw('COUNT(*) as total')->groupBy('status')->pluck('total', 'status')->map(static fn (mixed $count): int => (int) $count)->all();
        $counts = array_replace(array_fill_keys(['queued', 'sending', 'submitted', 'rejected', 'retry_pending'], 0), $counts);
        $pending = $query->clone()->whereIn('status', ['queued', 'sending', 'retry_pending'])->min('created_at');
        $retry = $query->clone()->where('status', 'retry_pending')->min('next_attempt_at');

        return new FiscalSubmissionSummary($counts, array_sum($counts), $pending === null ? null : (string) $pending, $retry === null ? null : (string) $retry);
    }
}
