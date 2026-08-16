<?php

declare(strict_types=1);

namespace App\Modules\Shifts;

use App\Modules\Shifts\Application\Contracts\CashTenderRecorder;
use App\Modules\Shifts\Application\Contracts\OpenShiftCheckoutEligibility;
use App\Modules\Shifts\Application\Contracts\ShiftCashManager;
use App\Modules\Shifts\Application\DatabaseCashTenderRecorder;
use App\Modules\Shifts\Application\DatabaseOpenShiftCheckoutEligibility;
use App\Modules\Shifts\Application\DatabaseShiftCashManager;
use Illuminate\Support\ServiceProvider;

final class ShiftsServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->scoped(ShiftCashManager::class, DatabaseShiftCashManager::class);
        $this->app->scoped(OpenShiftCheckoutEligibility::class, DatabaseOpenShiftCheckoutEligibility::class);
        $this->app->scoped(CashTenderRecorder::class, DatabaseCashTenderRecorder::class);
    }

    public function boot(): void
    {
        $this->loadMigrationsFrom(__DIR__.'/Database/Migrations');
        $this->loadRoutesFrom(__DIR__.'/Routes/api.php');
    }
}
