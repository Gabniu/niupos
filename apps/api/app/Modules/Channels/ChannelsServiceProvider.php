<?php

declare(strict_types=1);

namespace App\Modules\Channels;

use App\Modules\Channels\Application\Contracts\ChannelRegistrationManager;
use App\Modules\Channels\Application\DatabaseChannelRegistrationManager;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;

final class ChannelsServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->scoped(ChannelRegistrationManager::class, DatabaseChannelRegistrationManager::class);
    }

    public function boot(): void
    {
        RateLimiter::for('channel-registration', fn (Request $request): Limit => Limit::perMinute(30)->by(
            (string) $request->attributes->get('iam_session_id').'|'.$request->ip(),
        ));
        $this->loadMigrationsFrom(__DIR__.'/Database/Migrations');
        $this->loadRoutesFrom(__DIR__.'/Routes/api.php');
    }
}
