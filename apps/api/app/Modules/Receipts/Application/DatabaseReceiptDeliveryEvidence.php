<?php

declare(strict_types=1);

namespace App\Modules\Receipts\Application;

use App\Modules\Receipts\Application\Contracts\ReceiptDeliveryEvidence;
use App\Modules\Receipts\Domain\Receipt;
use App\Modules\Receipts\Domain\ReceiptDeliveryAttempt;
use App\Modules\Tenancy\Application\TenantContext;
use DateTimeInterface;
use Illuminate\Support\Str;
use InvalidArgumentException;
use RuntimeException;

final readonly class DatabaseReceiptDeliveryEvidence implements ReceiptDeliveryEvidence
{
    public function __construct(private TenantContext $tenants) {}

    public function record(string $receiptId, string $channel, string $outcome, DateTimeInterface $attemptedAt, ?string $errorCode = null): string
    {
        $channel = strtolower(trim($channel));
        $outcome = strtolower(trim($outcome));
        if (! in_array($channel, ['printer', 'email', 'sms'], true) || ! in_array($outcome, ['pending', 'succeeded', 'failed'], true)) {
            throw new InvalidArgumentException('Unsupported delivery evidence.');
        }
        if (($outcome === 'failed') !== ($errorCode !== null && trim($errorCode) !== '') || ($errorCode !== null && strlen($errorCode) > 64)) {
            throw new InvalidArgumentException('Failed attempts require a bounded error code; other outcomes must not include one.');
        }
        $tenantId = (string) $this->tenants->id();
        if (! Receipt::query()->where('tenant_id', $tenantId)->whereKey($receiptId)->exists()) {
            throw new RuntimeException('Receipt delivery evidence cannot be recorded.');
        }
        $id = (string) Str::uuid();
        ReceiptDeliveryAttempt::query()->create(['id' => $id, 'tenant_id' => $tenantId, 'receipt_id' => $receiptId, 'channel' => $channel, 'outcome' => $outcome, 'attempted_at' => $attemptedAt, 'error_code' => $errorCode]);

        return $id;
    }
}
