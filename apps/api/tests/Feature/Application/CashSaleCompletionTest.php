<?php

declare(strict_types=1);

namespace Tests\Feature\Application;

use App\Application\Contracts\CashSaleCompletion;
use App\Modules\Payments\Application\Contracts\PaymentProcessor;
use App\Modules\Payments\Application\Data\PaymentResult;
use App\Modules\Receipts\Application\Contracts\ReceiptIssuer;
use App\Modules\Receipts\Application\IssuedReceipt;
use App\Modules\Sales\Application\Contracts\FinalizedSaleSnapshotReader;
use App\Modules\Sales\Application\FinalizedSaleSnapshot;
use App\Modules\Shifts\Application\Contracts\CashTenderRecorder;
use App\Modules\Shifts\Application\Data\RecordedCashTender;
use DateTimeImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Mockery;
use RuntimeException;
use Tests\TestCase;

final class CashSaleCompletionTest extends TestCase
{
    use RefreshDatabase;

    public function test_cash_completion_composes_payment_drawer_and_receipt_atomically(): void
    {
        $at = new DateTimeImmutable('2026-08-08T15:00:00+03:00');
        $snapshot = new FinalizedSaleSnapshot('sale-1', 'tenant-1', 'shift-1', 'register-1', 'warehouse-1', 'actor-1', 'KES', 1000, 160, 1160, $at, []);
        $sales = Mockery::mock(FinalizedSaleSnapshotReader::class);
        $sales->shouldReceive('resolve')->once()->with('sale-1')->andReturn($snapshot);
        $payments = Mockery::mock(PaymentProcessor::class);
        $payments->shouldReceive('initiate')->once()->withArgs(fn (...$args) => $args[0] === 'sale-1' && $args[1] === 'cash' && $args[2] === 1160 && $args[3] === 'KES' && $args[4] === 'actor-1')->andReturn(new PaymentResult('payment-1', 'sale-1', 'cash', 'succeeded', 1160, 'KES'));
        $cash = Mockery::mock(CashTenderRecorder::class);
        $cash->shouldReceive('record')->once()->andReturn(new RecordedCashTender('movement-1', 'shift-1', 'sale-1', 1160, 'KES'));
        $receipts = Mockery::mock(ReceiptIssuer::class);
        $receipts->shouldReceive('issue')->once()->andReturn(new IssuedReceipt('receipt-1', 'sale-1', 'register-1', 42, $at));
        $this->app->instance(FinalizedSaleSnapshotReader::class, $sales);
        $this->app->instance(PaymentProcessor::class, $payments);
        $this->app->instance(CashTenderRecorder::class, $cash);
        $this->app->instance(ReceiptIssuer::class, $receipts);

        $result = $this->app->make(CashSaleCompletion::class)->complete('sale-1', 'actor-1', 'complete-1', $at);
        self::assertSame(['payment-1', 'movement-1', 'receipt-1', 42, 1160, 'KES'], [$result->paymentAttemptId, $result->cashMovementId, $result->receiptId, $result->receiptNumber, $result->amountMinor, $result->currencyCode]);
    }

    public function test_cash_completion_rejects_an_actor_other_than_the_sale_actor_before_side_effects(): void
    {
        $at = new DateTimeImmutable;
        $sales = Mockery::mock(FinalizedSaleSnapshotReader::class);
        $sales->shouldReceive('resolve')->once()->andReturn(new FinalizedSaleSnapshot('sale-1', 'tenant-1', 'shift-1', 'register-1', 'warehouse-1', 'actor-1', 'KES', 1, 0, 1, $at, []));
        $this->app->instance(FinalizedSaleSnapshotReader::class, $sales);
        $this->app->instance(PaymentProcessor::class, Mockery::mock(PaymentProcessor::class)->shouldNotReceive('initiate')->getMock());
        $this->app->instance(CashTenderRecorder::class, Mockery::mock(CashTenderRecorder::class)->shouldNotReceive('record')->getMock());
        $this->app->instance(ReceiptIssuer::class, Mockery::mock(ReceiptIssuer::class)->shouldNotReceive('issue')->getMock());

        $this->expectException(RuntimeException::class);
        $this->app->make(CashSaleCompletion::class)->complete('sale-1', 'actor-2', 'complete-2', $at);
    }
}
