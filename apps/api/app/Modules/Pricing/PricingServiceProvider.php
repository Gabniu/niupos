<?php

declare(strict_types=1);

namespace App\Modules\Pricing;

use App\Modules\Pricing\Application\Contracts\CheckoutQuoteProvider;
use App\Modules\Pricing\Application\Contracts\PricingManager;
use App\Modules\Pricing\Application\DatabasePricingManager;
use Illuminate\Support\ServiceProvider;

final class PricingServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->scoped(PricingManager::class, DatabasePricingManager::class);
        $this->app->scoped(CheckoutQuoteProvider::class, DatabasePricingManager::class);
    }

    public function boot(): void
    {
        $this->loadMigrationsFrom(__DIR__.'/Database/Migrations');
    }
}
