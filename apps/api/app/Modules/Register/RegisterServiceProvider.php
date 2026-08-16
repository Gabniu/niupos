<?php

declare(strict_types=1);

namespace App\Modules\Register;

use App\Modules\Register\Application\Contracts\RegisterDeviceManager;
use App\Modules\Register\Application\DatabaseRegisterDeviceManager;
use Illuminate\Support\ServiceProvider;

final class RegisterServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->scoped(RegisterDeviceManager::class, DatabaseRegisterDeviceManager::class);
    }

    public function boot(): void
    {
        $this->loadMigrationsFrom(__DIR__.'/Database/Migrations');
    }
}
