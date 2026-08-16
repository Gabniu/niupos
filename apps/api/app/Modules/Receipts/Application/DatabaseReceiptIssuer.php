<?php

declare(strict_types=1);

namespace App\Modules\Receipts\Application;

use App\Modules\Receipts\Application\Contracts\ReceiptIssuer;
use App\Modules\Receipts\Application\Contracts\ReceiptSaleSnapshots;
use App\Modules\Receipts\Application\Contracts\ReceiptSettlementStatus;
use App\Modules\Receipts\Domain\Receipt;
use App\Modules\Receipts\Domain\ReceiptLine;
use App\Modules\Receipts\Domain\ReceiptSequence;
use App\Modules\Tenancy\Application\TenantContext;
use DateTimeImmutable;
use DateTimeInterface;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use InvalidArgumentException;
use RuntimeException;

final readonly class DatabaseReceiptIssuer implements ReceiptIssuer
{
    public function __construct(private TenantContext $tenants, private ReceiptSaleSnapshots $sales, private ReceiptSettlementStatus $settlements) {}

    public function issue(string $saleId, string $sellerId, string $idempotencyKey, DateTimeInterface $issuedAt): IssuedReceipt
    {
        $tenantId = (string) $this->tenants->id();
        $key = trim($idempotencyKey);
        if ($key === '' || strlen($key) > 128) {
            throw new InvalidArgumentException('A bounded idempotency key is required.');
        }
        $snapshot = $this->sales->finalized($saleId);
        if ($snapshot->tenantId !== $tenantId || $snapshot->saleId !== $saleId || $snapshot->sellerId !== $sellerId || $snapshot->lines === []) {
            throw new RuntimeException('Receipt cannot be issued for this sale.');
        }
        if (! $this->settlements->isFullyPaid($saleId, $snapshot->currencyCode, $snapshot->grossMinor)) {
            throw new RuntimeException('Receipt cannot be issued for this sale.');
        }
        $at = DateTimeImmutable::createFromInterface($issuedAt);
        $fingerprint = hash('sha256', json_encode([$saleId, $sellerId, $at->format(DATE_ATOM)], JSON_THROW_ON_ERROR));

        return DB::transaction(function () use ($tenantId, $snapshot, $key, $fingerprint, $at): IssuedReceipt {
            if (DB::getDriverName() === 'pgsql') {
                DB::select('SELECT pg_advisory_xact_lock(hashtextextended(?, 0))', ["receipt:{$tenantId}:{$key}"]);
                DB::select('SELECT pg_advisory_xact_lock(hashtextextended(?, 0))', ["receipt-sequence:{$tenantId}:{$snapshot->registerId}"]);
            }
            $existing = Receipt::query()->where('tenant_id', $tenantId)->where('idempotency_key', $key)->first();
            if ($existing instanceof Receipt) {
                if (! hash_equals((string) $existing->command_fingerprint, $fingerprint)) {
                    throw new RuntimeException('The idempotency key is already bound to another receipt.');
                }

                return $this->result($existing);
            }
            if (Receipt::query()->where('tenant_id', $tenantId)->where('sale_id', $snapshot->saleId)->exists()) {
                throw new RuntimeException('Receipt cannot be issued for this sale.');
            }
            $sequence = ReceiptSequence::query()->where('tenant_id', $tenantId)->where('register_id', $snapshot->registerId)->lockForUpdate()->first();
            if (! $sequence instanceof ReceiptSequence) {
                $sequence = ReceiptSequence::query()->create(['id' => (string) Str::uuid(), 'tenant_id' => $tenantId, 'register_id' => $snapshot->registerId, 'last_number' => 0]);
            }
            $number = (int) $sequence->last_number + 1;
            $sequence->forceFill(['last_number' => $number])->save();
            $receipt = Receipt::query()->create([
                'id' => (string) Str::uuid(), 'tenant_id' => $tenantId, 'sale_id' => $snapshot->saleId,
                'shift_id' => $snapshot->shiftId, 'register_id' => $snapshot->registerId, 'seller_id' => $snapshot->sellerId,
                'receipt_number' => $number, 'currency_code' => $snapshot->currencyCode,
                'net_minor' => $snapshot->netMinor, 'tax_minor' => $snapshot->taxMinor, 'gross_minor' => $snapshot->grossMinor,
                'idempotency_key' => $key, 'command_fingerprint' => $fingerprint, 'sale_finalized_at' => $snapshot->finalizedAt, 'issued_at' => $at,
            ]);
            foreach ($snapshot->lines as $line) {
                ReceiptLine::query()->create([
                    'id' => (string) Str::uuid(), 'tenant_id' => $tenantId, 'receipt_id' => $receipt->id,
                    'line_number' => $line->lineNumber, 'variant_id' => $line->variantId,
                    'description' => ($line->description !== null && trim($line->description) !== '') ? trim($line->description) : 'Item '.$line->variantId,
                    'quantity' => $line->quantity, 'unit_price_minor' => $line->unitPriceMinor,
                    'net_minor' => $line->netMinor, 'tax_minor' => $line->taxMinor, 'gross_minor' => $line->grossMinor,
                    'tax_code' => $line->taxCode, 'tax_rate_basis_points' => $line->taxRateBasisPoints, 'tax_inclusive' => $line->taxInclusive,
                ]);
            }

            return $this->result($receipt);
        });
    }

    private function result(Receipt $receipt): IssuedReceipt
    {
        return new IssuedReceipt((string) $receipt->id, (string) $receipt->sale_id, (string) $receipt->register_id, (int) $receipt->receipt_number, DateTimeImmutable::createFromInterface($receipt->issued_at));
    }
}
