<?php

declare(strict_types=1);

namespace App\Application;

use App\Application\Contracts\CashSaleCompletion;
use App\Application\Data\CompletedCashSale;
use App\Modules\Payments\Application\Contracts\PaymentProcessor;
use App\Modules\Receipts\Application\Contracts\ReceiptIssuer;
use App\Modules\Sales\Application\Contracts\FinalizedSaleSnapshotReader;
use App\Modules\Shifts\Application\Contracts\CashTenderRecorder;
use DateTimeInterface;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;
use RuntimeException;

final readonly class DatabaseCashSaleCompletion implements CashSaleCompletion
{
    public function __construct(
        private FinalizedSaleSnapshotReader $sales,
        private PaymentProcessor $payments,
        private CashTenderRecorder $cash,
        private ReceiptIssuer $receipts,
    ) {}

    public function complete(string $saleId, string $actorUserId, string $idempotencyKey, DateTimeInterface $completedAt): CompletedCashSale
    {
        $key = trim($idempotencyKey);
        if ($key === '' || strlen($key) > 128) {
            throw new InvalidArgumentException('A bounded completion idempotency key is required.');
        }

        return DB::transaction(function () use ($saleId, $actorUserId, $key, $completedAt): CompletedCashSale {
            $sale = $this->sales->resolve($saleId);
            if ($sale->actorUserId !== $actorUserId) {
                throw new RuntimeException('Cash sale completion is unavailable.');
            }
            $payment = $this->payments->initiate($saleId, 'cash', $sale->grossMinor, $sale->currencyCode, $actorUserId, $this->derivedKey('payment', $key));
            if ($payment->status !== 'succeeded') {
                throw new RuntimeException('Cash payment did not complete.');
            }
            $movement = $this->cash->record($sale->shiftId, $saleId, $actorUserId, $sale->grossMinor, $sale->currencyCode, $this->derivedKey('drawer', $key));
            $receipt = $this->receipts->issue($saleId, $actorUserId, $this->derivedKey('receipt', $key), $completedAt);

            return new CompletedCashSale($saleId, $payment->attemptId, $movement->movementId, $receipt->receiptId, $receipt->receiptNumber, $sale->grossMinor, $sale->currencyCode);
        });
    }

    private function derivedKey(string $purpose, string $key): string
    {
        return "cash-{$purpose}:".hash('sha256', $key);
    }
}
