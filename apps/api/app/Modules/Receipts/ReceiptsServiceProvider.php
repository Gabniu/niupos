<?php

declare(strict_types=1);

namespace App\Modules\Receipts;

use App\Modules\Receipts\Application\Contracts\ReceiptDeliveryEvidence;
use App\Modules\Receipts\Application\Contracts\ReceiptIssuer;
use App\Modules\Receipts\Application\Contracts\ReceiptReader;
use App\Modules\Receipts\Application\DatabaseReceiptDeliveryEvidence;
use App\Modules\Receipts\Application\DatabaseReceiptIssuer;
use App\Modules\Receipts\Application\DatabaseReceiptReader;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;

final class ReceiptsServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->scoped(ReceiptIssuer::class, DatabaseReceiptIssuer::class);
        $this->app->scoped(ReceiptDeliveryEvidence::class, DatabaseReceiptDeliveryEvidence::class);
        $this->app->scoped(ReceiptReader::class, DatabaseReceiptReader::class);
    }

    public function boot(): void
    {
        $this->loadMigrationsFrom(__DIR__.'/Database/Migrations');
        $this->loadRoutesFrom(__DIR__.'/Routes/api.php');
        RateLimiter::for('receipts-read', fn (Request $request): Limit => Limit::perMinute(120)->by((string) $request->attributes->get('iam_session_id').'|'.$request->ip()));
        RateLimiter::for('receipts-delivery', fn (Request $request): Limit => Limit::perMinute(60)->by((string) $request->attributes->get('iam_session_id').'|'.$request->ip()));
    }
}
