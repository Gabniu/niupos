<?php

declare(strict_types=1);

namespace App\Modules\Sales;

use App\Modules\Sales\Application\Contracts\FinalizedSaleSnapshotReader;
use App\Modules\Sales\Application\Contracts\SalesCheckout;
use App\Modules\Sales\Application\DatabaseFinalizedSaleSnapshotReader;
use App\Modules\Sales\Application\DatabaseSalesCheckout;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;

final class SalesServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->scoped(SalesCheckout::class, DatabaseSalesCheckout::class);
        $this->app->scoped(FinalizedSaleSnapshotReader::class, DatabaseFinalizedSaleSnapshotReader::class);
    }

    public function boot(): void
    {
        $this->loadMigrationsFrom(__DIR__.'/Database/Migrations');
        $this->loadRoutesFrom(__DIR__.'/Routes/api.php');
        RateLimiter::for('sales-checkout', fn (Request $request): Limit => Limit::perMinute(60)->by(
            (string) $request->attributes->get('iam_session_id').'|'.$request->ip(),
        ));
    }
}
