<?php

declare(strict_types=1);

namespace App\Modules\Fiscal\Application;

use App\Modules\Fiscal\Application\Contracts\FiscalSubmissionQueue;
use App\Modules\Fiscal\Application\Data\FiscalInvoice;
use App\Modules\Fiscal\Application\Data\FiscalSubmission;
use App\Modules\Tenancy\Application\TenantContext;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use RuntimeException;

final readonly class DatabaseFiscalSubmissionQueue implements FiscalSubmissionQueue
{
    public function __construct(private TenantContext $tenantContext) {}

    public function enqueue(FiscalInvoice $invoice): FiscalSubmission
    {
        $tenantId = (string) $this->tenantContext->id();
        $fingerprint = hash('sha256', json_encode([
            'saleId' => $invoice->saleId,
            'profile' => $invoice->profile,
            'currencyCode' => $invoice->normalizedCurrency(),
            'netMinor' => $invoice->netMinor,
            'taxMinor' => $invoice->taxMinor,
            'grossMinor' => $invoice->grossMinor,
            'idempotencyKey' => $invoice->idempotencyKey,
            'payload' => $invoice->payload,
        ], JSON_THROW_ON_ERROR));

        return DB::transaction(function () use ($tenantId, $invoice, $fingerprint): FiscalSubmission {
            $this->lock("fiscal-enqueue:{$tenantId}:{$invoice->saleId}");
            $existing = DB::table('fiscal_invoice_submissions')->where('tenant_id', $tenantId)->where('sale_id', $invoice->saleId)->first();
            if ($existing !== null) {
                if (! hash_equals((string) $existing->payload_fingerprint, $fingerprint)) {
                    throw new RuntimeException('A different fiscal invoice is already queued for this sale.');
                }

                return $this->submission($existing);
            }

            $id = (string) Str::uuid();
            DB::table('fiscal_invoice_submissions')->insert([
                'id' => $id,
                'tenant_id' => $tenantId,
                'sale_id' => $invoice->saleId,
                'profile' => $invoice->profile,
                'currency_code' => $invoice->normalizedCurrency(),
                'net_minor' => $invoice->netMinor,
                'tax_minor' => $invoice->taxMinor,
                'gross_minor' => $invoice->grossMinor,
                'idempotency_key' => $invoice->idempotencyKey,
                'payload' => json_encode($invoice->payload, JSON_THROW_ON_ERROR),
                'payload_fingerprint' => $fingerprint,
                'status' => 'queued',
                'attempts' => 0,
                'next_attempt_at' => now(),
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            return new FiscalSubmission($id, $invoice->saleId, $invoice->profile, 'queued', 0, null, null);
        });
    }

    public function findForSale(string $saleId): ?FiscalSubmission
    {
        $row = DB::table('fiscal_invoice_submissions')
            ->where('tenant_id', (string) $this->tenantContext->id())
            ->where('sale_id', trim($saleId))
            ->first();

        return $row === null ? null : $this->submission($row);
    }

    private function submission(object $row): FiscalSubmission
    {
        return new FiscalSubmission(
            (string) $row->id,
            (string) $row->sale_id,
            (string) $row->profile,
            (string) $row->status,
            (int) $row->attempts,
            $row->provider_reference === null ? null : (string) $row->provider_reference,
            $row->last_result_code === null ? null : (string) $row->last_result_code,
        );
    }

    private function lock(string $identity): void
    {
        if (DB::getDriverName() === 'pgsql') {
            DB::select('SELECT pg_advisory_xact_lock(hashtextextended(?, 0))', [$identity]);
        }
    }
}
