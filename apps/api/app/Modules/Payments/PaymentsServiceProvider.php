<?php

declare(strict_types=1);

namespace App\Modules\Payments;

use App\Modules\Payments\Application\Contracts\PaymentProcessor;
use App\Modules\Payments\Application\Contracts\PaymentReconciliationReader;
use App\Modules\Payments\Application\Contracts\PaymentSettlementReader;
use App\Modules\Payments\Application\DatabasePaymentReconciliationReader;
use App\Modules\Payments\Application\DatabasePaymentProcessor;
use App\Modules\Payments\Application\DatabasePaymentSettlementReader;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;

final class PaymentsServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->scoped(PaymentProcessor::class, DatabasePaymentProcessor::class);
        $this->app->scoped(PaymentReconciliationReader::class, DatabasePaymentReconciliationReader::class);
        $this->app->scoped(PaymentSettlementReader::class, DatabasePaymentSettlementReader::class);
    }

    public function boot(): void
    {
        $this->loadMigrationsFrom(__DIR__.'/Database/Migrations');
        $this->loadRoutesFrom(__DIR__.'/Routes/api.php');
        RateLimiter::for('payments-operations', fn (Request $request): Limit => Limit::perMinute(60)->by(
            (string) $request->attributes->get('iam_session_id').'|'.$request->ip(),
        ));
        RateLimiter::for('payments-provider-results', fn (Request $request): Limit => Limit::perMinute(30)->by(
            (string) $request->attributes->get('iam_session_id').'|'.$request->ip(),
        ));
    }
}
