<?php

declare(strict_types=1);

namespace App\Modules\Receipts\Application;

use App\Modules\Receipts\Application\Contracts\ReceiptReader;
use App\Modules\Tenancy\Application\TenantContext;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;

final readonly class DatabaseReceiptReader implements ReceiptReader
{
    public function __construct(private TenantContext $tenants) {}

    public function find(string $receiptId): ?ReceiptView
    {
        $tenantId = (string) $this->tenants->id();
        $receipt = DB::table('receipts')->where('tenant_id', $tenantId)->where('id', $receiptId)->first();
        if ($receipt === null) {
            return null;
        }
        $lines = DB::table('receipt_lines')->where('tenant_id', $tenantId)->where('receipt_id', $receiptId)
            ->orderBy('line_number')->get()->map(static fn (object $line): array => [
                'line_number' => (int) $line->line_number, 'variant_id' => (string) $line->variant_id,
                'description' => (string) $line->description, 'quantity' => (int) $line->quantity,
                'unit_price_minor' => (int) $line->unit_price_minor, 'net_minor' => (int) $line->net_minor,
                'tax_minor' => (int) $line->tax_minor, 'gross_minor' => (int) $line->gross_minor,
                'tax_code' => (string) $line->tax_code, 'tax_rate_basis_points' => (int) $line->tax_rate_basis_points,
                'tax_inclusive' => (bool) $line->tax_inclusive,
            ])->all();

        return new ReceiptView(
            (string) $receipt->id, (string) $receipt->sale_id, (string) $receipt->shift_id,
            (string) $receipt->register_id, (string) $receipt->seller_id, (int) $receipt->receipt_number,
            (string) $receipt->currency_code, (int) $receipt->net_minor, (int) $receipt->tax_minor,
            (int) $receipt->gross_minor, CarbonImmutable::parse($receipt->sale_finalized_at)->toAtomString(), CarbonImmutable::parse($receipt->issued_at)->toAtomString(), $lines,
        );
    }
}
