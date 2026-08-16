<?php

declare(strict_types=1);

namespace App\Modules\Sync;

use App\Modules\Sync\Application\Contracts\SyncBootstrap;
use App\Modules\Sync\Application\Contracts\SyncChangePublisher;
use App\Modules\Sync\Application\Contracts\SyncCommandHandler;
use App\Modules\Sync\Application\Contracts\SyncProtocol;
use App\Modules\Sync\Application\DatabaseSyncBootstrap;
use App\Modules\Sync\Application\DatabaseSyncProtocol;
use App\Modules\Sync\Application\DeferredSyncChangePublisher;
use App\Modules\Sync\Application\SalesSyncCommandHandler;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;

final class SyncServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->scoped(SyncProtocol::class, DatabaseSyncProtocol::class);
        $this->app->scoped(SyncChangePublisher::class, DeferredSyncChangePublisher::class);
        $this->app->scoped(SyncBootstrap::class, DatabaseSyncBootstrap::class);
        $this->app->scoped(SyncCommandHandler::class, SalesSyncCommandHandler::class);
    }

    public function boot(): void
    {
        $this->loadMigrationsFrom(__DIR__.'/Database/Migrations');
        $this->loadRoutesFrom(__DIR__.'/Routes/api.php');
        RateLimiter::for('sync-pull', fn (Request $request): Limit => Limit::perMinute(120)->by($this->rateLimitKey($request)));
        RateLimiter::for('sync-bootstrap', fn (Request $request): Limit => Limit::perMinute(10)->by($this->rateLimitKey($request)));
        RateLimiter::for('sync-commands', fn (Request $request): Limit => Limit::perMinute(60)->by($this->rateLimitKey($request)));
    }

    private function rateLimitKey(Request $request): string
    {
        $device = $request->header('X-Device-Id');

        return (string) $request->attributes->get('iam_session_id').'|'.(is_string($device) && strlen($device) <= 36 ? strtolower($device) : 'invalid').'|'.$request->ip();
    }
}
