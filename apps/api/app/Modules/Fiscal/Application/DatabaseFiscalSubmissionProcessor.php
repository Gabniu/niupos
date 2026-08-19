<?php

declare(strict_types=1);

namespace App\Modules\Fiscal\Application;

use App\Modules\Fiscal\Application\Contracts\FiscalGateway;
use App\Modules\Fiscal\Application\Contracts\FiscalSubmissionProcessor;
use App\Modules\Fiscal\Application\Data\FiscalGatewayResult;
use App\Modules\Fiscal\Application\Data\FiscalInvoice;
use App\Modules\Tenancy\Application\TenantContext;
use Illuminate\Support\Facades\DB;
use Throwable;

final readonly class DatabaseFiscalSubmissionProcessor implements FiscalSubmissionProcessor
{
    public function __construct(private TenantContext $tenantContext, private FiscalGateway $gateway) {}

    public function processDue(int $limit = 50): int
    {
        $limit = max(1, min(100, $limit));
        $tenantId = (string) $this->tenantContext->id();
        $rows = DB::table('fiscal_invoice_submissions')
            ->where('tenant_id', $tenantId)
            ->whereIn('status', ['queued', 'retry_pending'])
            ->where('next_attempt_at', '<=', now())
            ->orderBy('next_attempt_at')
            ->limit($limit)
            ->get();
        $processed = 0;

        foreach ($rows as $row) {
            $claimed = DB::transaction(function () use ($tenantId, $row): bool {
                $updated = DB::table('fiscal_invoice_submissions')
                    ->where('tenant_id', $tenantId)
                    ->where('id', $row->id)
                    ->whereIn('status', ['queued', 'retry_pending'])
                    ->update(['status' => 'sending', 'attempts' => ((int) $row->attempts) + 1, 'updated_at' => now()]);

                return $updated === 1;
            });
            if (! $claimed) {
                continue;
            }

            $result = $this->submit($row);
            $this->record($tenantId, (string) $row->id, (int) $row->attempts + 1, $result);
            $processed += 1;
        }

        return $processed;
    }

    private function submit(object $row): FiscalGatewayResult
    {
        try {
            return $this->gateway->submit(new FiscalInvoice(
                (string) $row->sale_id,
                (string) $row->profile,
                (string) $row->currency_code,
                (int) $row->net_minor,
                (int) $row->tax_minor,
                (int) $row->gross_minor,
                (string) $row->idempotency_key,
                json_decode((string) $row->payload, true, flags: JSON_THROW_ON_ERROR),
            ));
        } catch (Throwable) {
            return new FiscalGatewayResult('retry_pending', resultCode: 'gateway_unavailable', errorMessage: 'Fiscal gateway unavailable.');
        }
    }

    private function record(string $tenantId, string $id, int $attempts, FiscalGatewayResult $result): void
    {
        $retry = $result->status === 'retry_pending';
        DB::table('fiscal_invoice_submissions')
            ->where('tenant_id', $tenantId)
            ->where('id', $id)
            ->where('status', 'sending')
            ->update([
                'status' => $result->status,
                'attempts' => $attempts,
                'next_attempt_at' => $retry ? now()->addSeconds(min(3600, 2 ** min($attempts, 12))) : null,
                'provider_reference' => $result->providerReference,
                'last_result_code' => $result->resultCode,
                'last_error' => $result->errorMessage,
                'submitted_at' => $result->status === 'submitted' ? now() : null,
                'updated_at' => now(),
            ]);
    }
}
