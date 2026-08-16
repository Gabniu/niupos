<?php

namespace App\Providers;

use App\Application\Contracts\CashSaleCompletion;
use App\Application\DatabaseCashSaleCompletion;
use App\Integration\PaymentsReceiptSettlementAdapter;
use App\Integration\SalesPaymentLookupAdapter;
use App\Integration\SalesReceiptSnapshotsAdapter;
use App\Modules\Payments\Application\Contracts\SalePaymentLookup;
use App\Modules\Receipts\Application\Contracts\ReceiptSaleSnapshots;
use App\Modules\Receipts\Application\Contracts\ReceiptSettlementStatus;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->app->scoped(SalePaymentLookup::class, SalesPaymentLookupAdapter::class);
        $this->app->scoped(ReceiptSaleSnapshots::class, SalesReceiptSnapshotsAdapter::class);
        $this->app->scoped(ReceiptSettlementStatus::class, PaymentsReceiptSettlementAdapter::class);
        $this->app->scoped(CashSaleCompletion::class, DatabaseCashSaleCompletion::class);
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        //
    }
}
