<?php

declare(strict_types=1);

namespace App\Modules\Sales\Application;

use App\Modules\Audit\Application\Contracts\SecurityAuditRecorder;
use App\Modules\Audit\Application\SecurityAuditEvent;
use App\Modules\Inventory\Application\Contracts\InventorySaleIntent;
use App\Modules\Pricing\Application\CheckoutLineQuote;
use App\Modules\Pricing\Application\Contracts\CheckoutQuoteProvider;
use App\Modules\Sales\Application\Contracts\SalesCheckout;
use App\Modules\Sales\Domain\Sale;
use App\Modules\Sales\Domain\SaleLine;
use App\Modules\Shifts\Application\Contracts\OpenShiftCheckoutEligibility;
use App\Modules\Shifts\Application\Data\EligibleOpenShift;
use App\Modules\Tenancy\Application\TenantContext;
use DateTimeImmutable;
use DateTimeInterface;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use InvalidArgumentException;
use RuntimeException;

final readonly class DatabaseSalesCheckout implements SalesCheckout
{
    public function __construct(
        private TenantContext $tenants,
        private OpenShiftCheckoutEligibility $shifts,
        private CheckoutQuoteProvider $pricing,
        private InventorySaleIntent $inventory,
        private SecurityAuditRecorder $audit,
    ) {}

    public function finalize(
        string $registerId,
        string $actorUserId,
        string $warehouseId,
        string $priceBookId,
        string $currencyCode,
        array $lines,
        string $idempotencyKey,
        DateTimeInterface $occurredAt,
    ): FinalizedSale {
        $tenantId = (string) $this->tenants->id();
        $currency = strtoupper(trim($currencyCode));
        $key = trim($idempotencyKey);
        if (! preg_match('/^[A-Z]{3}$/', $currency) || $key === '' || strlen($key) > 128) {
            throw new InvalidArgumentException('A currency and bounded idempotency key are required.');
        }
        $normalizedLines = $this->normalizeLines($lines);
        $at = DateTimeImmutable::createFromInterface($occurredAt);
        $fingerprint = hash('sha256', json_encode([
            $registerId, $actorUserId, $warehouseId, $priceBookId, $currency,
            $at->format(DATE_ATOM), $normalizedLines,
        ], JSON_THROW_ON_ERROR));

        return $this->shifts->withEligibleOpenShift(
            $registerId,
            $actorUserId,
            fn (EligibleOpenShift $shift): FinalizedSale => $this->withinTransaction(
                $tenantId,
                $shift,
                $warehouseId,
                $priceBookId,
                $currency,
                $normalizedLines,
                $key,
                $fingerprint,
                $at,
            ),
        );
    }

    /**
     * @param  list<array{variant_id: string, quantity: int}>  $lines
     * @return list<array{variant_id: string, quantity: int}>
     */
    private function normalizeLines(array $lines): array
    {
        if ($lines === []) {
            throw new InvalidArgumentException('At least one sale line is required.');
        }
        $normalized = [];
        foreach ($lines as $line) {
            if (! is_array($line) || ! isset($line['variant_id'], $line['quantity']) || ! is_string($line['variant_id']) || ! is_int($line['quantity']) || $line['quantity'] <= 0) {
                throw new InvalidArgumentException('Every sale line requires a variant and positive integer quantity.');
            }
            if (isset($normalized[$line['variant_id']])) {
                throw new InvalidArgumentException('Duplicate variants must be combined before checkout.');
            }
            $normalized[$line['variant_id']] = ['variant_id' => $line['variant_id'], 'quantity' => $line['quantity']];
        }
        ksort($normalized);

        return array_values($normalized);
    }

    /** @param list<array{variant_id: string, quantity: int}> $lines */
    private function withinTransaction(
        string $tenantId,
        EligibleOpenShift $shift,
        string $warehouseId,
        string $priceBookId,
        string $currency,
        array $lines,
        string $key,
        string $fingerprint,
        DateTimeImmutable $at,
    ): FinalizedSale {
        if ($shift->tenantId !== $tenantId || $shift->currency !== $currency) {
            throw new RuntimeException('Checkout is not eligible for the active shift.');
        }

        return DB::transaction(function () use ($tenantId, $shift, $warehouseId, $priceBookId, $currency, $lines, $key, $fingerprint, $at): FinalizedSale {
            if (DB::getDriverName() === 'pgsql') {
                DB::select('SELECT pg_advisory_xact_lock(hashtextextended(?, 0))', ["sales:{$tenantId}:{$key}"]);
            }
            $existing = Sale::query()->where('tenant_id', $tenantId)->where('idempotency_key', $key)->first();
            if ($existing instanceof Sale) {
                if (! hash_equals((string) $existing->command_fingerprint, $fingerprint)) {
                    throw new RuntimeException('The idempotency key is already bound to another checkout.');
                }

                return $this->result($existing);
            }

            $saleId = (string) Str::uuid();
            $quotes = [];
            $net = $tax = $gross = 0;
            foreach ($lines as $line) {
                $quote = $this->pricing->quote($priceBookId, $line['variant_id'], $line['quantity'], $currency, $at);
                $quotes[] = $quote;
                $net = $this->checkedAdd($net, $quote->netMinor);
                $tax = $this->checkedAdd($tax, $quote->taxMinor);
                $gross = $this->checkedAdd($gross, $quote->grossMinor);
            }

            $reservations = [];
            foreach ($quotes as $index => $quote) {
                $reservationId = $this->deterministicUuid("{$tenantId}|{$key}|{$quote->variantId}");
                $operationKey = 'sale-reserve:'.hash('sha256', "{$tenantId}|{$key}|{$index}");
                $this->inventory->reserve($reservationId, $warehouseId, $quote->variantId, $quote->quantity, $operationKey);
                $reservations[] = $reservationId;
            }
            foreach ($reservations as $index => $reservationId) {
                $this->inventory->finalize($reservationId, 'sale-finalize:'.hash('sha256', "{$tenantId}|{$key}|{$index}"));
            }

            $sale = Sale::query()->create([
                'id' => $saleId,
                'tenant_id' => $tenantId,
                'shift_id' => $shift->shiftId,
                'register_id' => $shift->registerId,
                'warehouse_id' => $warehouseId,
                'actor_user_id' => $shift->actorUserId,
                'status' => 'finalized',
                'currency_code' => $currency,
                'net_minor' => $net,
                'tax_minor' => $tax,
                'gross_minor' => $gross,
                'idempotency_key' => $key,
                'command_fingerprint' => $fingerprint,
                'finalized_at' => $at,
            ]);
            foreach ($quotes as $index => $quote) {
                $this->createLine($tenantId, $saleId, $reservations[$index], $index + 1, $quote);
            }
            $this->audit->record(new SecurityAuditEvent('sales.sale.finalized', $shift->actorUserId, [
                'sale_id' => $saleId,
                'shift_id' => $shift->shiftId,
                'gross_minor' => $gross,
                'currency' => $currency,
                'line_count' => count($quotes),
            ], $tenantId));

            return $this->result($sale);
        });
    }

    private function createLine(string $tenantId, string $saleId, string $reservationId, int $lineNumber, CheckoutLineQuote $quote): void
    {
        SaleLine::query()->create([
            'id' => (string) Str::uuid(), 'tenant_id' => $tenantId, 'sale_id' => $saleId,
            'variant_id' => $quote->variantId, 'reservation_id' => $reservationId, 'line_number' => $lineNumber,
            'quantity' => $quote->quantity, 'currency_code' => $quote->currencyCode,
            'unit_price_minor' => $quote->unitPriceMinor, 'net_minor' => $quote->netMinor,
            'tax_minor' => $quote->taxMinor, 'gross_minor' => $quote->grossMinor,
            'tax_category_id' => $quote->taxCategoryId, 'tax_code' => $quote->taxCode,
            'tax_rate_basis_points' => $quote->taxRateBasisPoints, 'tax_inclusive' => $quote->taxInclusive,
            'price_book_id' => $quote->priceBookId, 'price_id' => $quote->priceId, 'quoted_at' => $quote->quotedAt,
        ]);
    }

    private function result(Sale $sale): FinalizedSale
    {
        return new FinalizedSale((string) $sale->id, (string) $sale->shift_id, (string) $sale->register_id, (string) $sale->currency_code, (int) $sale->net_minor, (int) $sale->tax_minor, (int) $sale->gross_minor, SaleLine::query()->where('tenant_id', $sale->tenant_id)->where('sale_id', $sale->id)->count(), DateTimeImmutable::createFromInterface($sale->finalized_at));
    }

    private function checkedAdd(int $left, int $right): int
    {
        if ($right > 0 && $left > PHP_INT_MAX - $right) {
            throw new RuntimeException('Sale total exceeds the supported integer range.');
        }

        return $left + $right;
    }

    private function deterministicUuid(string $value): string
    {
        $hex = substr(hash('sha256', $value), 0, 32);
        $hex[12] = '5';
        $hex[16] = dechex((hexdec($hex[16]) & 0x3) | 0x8);

        return sprintf('%s-%s-%s-%s-%s', substr($hex, 0, 8), substr($hex, 8, 4), substr($hex, 12, 4), substr($hex, 16, 4), substr($hex, 20, 12));
    }
}
