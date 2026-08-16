<?php

declare(strict_types=1);

namespace App\Modules\Payments\Application;

use App\Modules\Payments\Application\Contracts\PaymentProcessor;
use App\Modules\Payments\Application\Contracts\SalePaymentLookup;
use App\Modules\Payments\Application\Data\PayableSale;
use App\Modules\Payments\Application\Data\PaymentResult;
use App\Modules\Payments\Domain\PaymentAllocation;
use App\Modules\Payments\Domain\PaymentAttempt;
use App\Modules\Tenancy\Application\TenantContext;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;
use RuntimeException;

final readonly class DatabasePaymentProcessor implements PaymentProcessor
{
    public function __construct(private TenantContext $tenants, private SalePaymentLookup $sales) {}

    public function initiate(string $saleId, string $method, int $amountMinor, string $currencyCode, string $actorUserId, string $idempotencyKey, array $providerMetadata = []): PaymentResult
    {
        $tenantId = (string) $this->tenants->id();
        $method = strtolower(trim($method));
        $currency = strtoupper(trim($currencyCode));
        $key = trim($idempotencyKey);
        if (! in_array($method, ['cash', 'mpesa'], true) || $amountMinor <= 0 || ! preg_match('/^[A-Z]{3}$/', $currency) || $key === '' || strlen($key) > 128) {
            throw new InvalidArgumentException('A supported method, positive amount, currency and bounded idempotency key are required.');
        }
        $metadata = $this->normalizeMetadata($method, $providerMetadata);
        $fingerprint = hash('sha256', json_encode([$saleId, $method, $amountMinor, $currency, $actorUserId, $metadata], JSON_THROW_ON_ERROR));

        return DB::transaction(function () use ($tenantId, $saleId, $method, $amountMinor, $currency, $actorUserId, $key, $metadata, $fingerprint): PaymentResult {
            $this->lock("payment-initiate:{$tenantId}:{$key}");
            $existing = PaymentAttempt::query()->where('tenant_id', $tenantId)->where('idempotency_key', $key)->first();
            if ($existing instanceof PaymentAttempt) {
                if (! hash_equals((string) $existing->command_fingerprint, $fingerprint)) {
                    throw new RuntimeException('The idempotency key is already bound to another payment.');
                }

                return $this->result($existing);
            }

            $sale = $this->sales->finalized($saleId);
            $this->assertPayable($sale, $tenantId, $amountMinor, $currency);
            $attempt = PaymentAttempt::query()->create([
                'tenant_id' => $tenantId, 'sale_id' => $saleId, 'actor_user_id' => $actorUserId,
                'method' => $method, 'status' => $method === 'cash' ? 'succeeded' : 'pending',
                'amount_minor' => $amountMinor, 'currency_code' => $currency,
                'idempotency_key' => $key, 'command_fingerprint' => $fingerprint,
                'provider_metadata' => $metadata, 'completed_at' => $method === 'cash' ? now() : null,
            ]);
            if ($method === 'cash') {
                $this->allocate($attempt, $sale);
            }

            return $this->result($attempt);
        });
    }

    public function applyProviderResult(string $attemptId, string $status, string $providerReference, string $resultFingerprint): PaymentResult
    {
        $tenantId = (string) $this->tenants->id();
        $status = strtolower(trim($status));
        $reference = trim($providerReference);
        $fingerprint = strtolower(trim($resultFingerprint));
        if (! in_array($status, ['succeeded', 'failed'], true) || $reference === '' || strlen($reference) > 128 || ! preg_match('/^[a-f0-9]{64}$/', $fingerprint)) {
            throw new InvalidArgumentException('A terminal status, bounded provider reference and SHA-256 result fingerprint are required.');
        }

        return DB::transaction(function () use ($tenantId, $attemptId, $status, $reference, $fingerprint): PaymentResult {
            $this->lock("payment-result:{$tenantId}:{$attemptId}");
            $attempt = PaymentAttempt::query()->where('tenant_id', $tenantId)->whereKey($attemptId)->lockForUpdate()->firstOrFail();
            if ($attempt->method !== 'mpesa') {
                throw new RuntimeException('Provider results apply only to M-Pesa attempts.');
            }
            if ($attempt->status !== 'pending') {
                if ($attempt->status === $status && hash_equals((string) $attempt->provider_reference, $reference) && hash_equals((string) $attempt->provider_result_fingerprint, $fingerprint)) {
                    return $this->result($attempt);
                }
                throw new RuntimeException('The payment attempt already has a different terminal result.');
            }

            $sale = $this->sales->finalized((string) $attempt->sale_id);
            $this->assertPayable($sale, $tenantId, (int) $attempt->amount_minor, (string) $attempt->currency_code);
            $attempt->update(['status' => $status, 'provider_reference' => $reference, 'provider_result_fingerprint' => $fingerprint, 'completed_at' => now()]);
            if ($status === 'succeeded') {
                $this->allocate($attempt, $sale);
            }

            return $this->result($attempt->refresh());
        });
    }

    /** @param array<string, scalar|null> $metadata @return array<string, string> */
    private function normalizeMetadata(string $method, array $metadata): array
    {
        if ($method === 'cash') {
            if ($metadata !== []) {
                throw new InvalidArgumentException('Cash payments do not accept provider metadata.');
            }

            return [];
        }
        $allowed = ['customer_reference', 'request_reference'];
        $normalized = [];
        foreach ($metadata as $name => $value) {
            if (! is_string($name) || ! in_array($name, $allowed, true) || (! is_string($value) && ! is_int($value))) {
                throw new InvalidArgumentException('Provider metadata contains a forbidden or unsafe field.');
            }
            $text = trim((string) $value);
            if ($text === '' || strlen($text) > 128) {
                throw new InvalidArgumentException('Provider metadata values must be bounded references.');
            }
            $normalized[$name] = $text;
        }
        ksort($normalized);

        return $normalized;
    }

    private function assertPayable(PayableSale $sale, string $tenantId, int $amountMinor, string $currency): void
    {
        if ($sale->tenantId !== $tenantId) {
            throw new RuntimeException('The finalized sale is unavailable in this tenant.');
        }
        if ($sale->grossMinor !== $amountMinor || strtoupper($sale->currencyCode) !== $currency) {
            throw new RuntimeException('The initial payment policy requires the exact sale gross and currency.');
        }
    }

    private function allocate(PaymentAttempt $attempt, PayableSale $sale): void
    {
        $allocated = (int) PaymentAllocation::query()->where('tenant_id', $attempt->tenant_id)->where('sale_id', $sale->saleId)->lockForUpdate()->sum('amount_minor');
        if ($allocated > $sale->grossMinor - (int) $attempt->amount_minor) {
            throw new RuntimeException('Succeeded payment allocations cannot exceed the sale gross.');
        }
        PaymentAllocation::query()->create([
            'tenant_id' => $attempt->tenant_id, 'payment_attempt_id' => $attempt->id,
            'sale_id' => $sale->saleId, 'amount_minor' => $attempt->amount_minor,
            'currency_code' => $attempt->currency_code,
        ]);
    }

    private function result(PaymentAttempt $attempt): PaymentResult
    {
        return new PaymentResult((string) $attempt->id, (string) $attempt->sale_id, (string) $attempt->method, (string) $attempt->status, (int) $attempt->amount_minor, (string) $attempt->currency_code, $attempt->provider_reference ? (string) $attempt->provider_reference : null);
    }

    private function lock(string $identity): void
    {
        if (DB::getDriverName() === 'pgsql') {
            DB::select('SELECT pg_advisory_xact_lock(hashtextextended(?, 0))', [$identity]);
        }
    }
}
