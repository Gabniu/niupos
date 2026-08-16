<?php

declare(strict_types=1);

namespace App\Modules\Inventory;

use App\Modules\Inventory\Application\Contracts\InventoryLedger;
use App\Modules\Inventory\Application\Contracts\InventorySaleIntent;
use App\Modules\Inventory\Application\DatabaseInventoryLedger;
use App\Modules\Inventory\Application\DatabaseInventorySaleIntent;
use Illuminate\Support\ServiceProvider;

final class InventoryServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->scoped(InventoryLedger::class, DatabaseInventoryLedger::class);
        $this->app->scoped(InventorySaleIntent::class, DatabaseInventorySaleIntent::class);
    }

    public function boot(): void
    {
        $this->loadMigrationsFrom(__DIR__.'/Database/Migrations');
        $this->loadRoutesFrom(__DIR__.'/Routes/api.php');
    }
}
