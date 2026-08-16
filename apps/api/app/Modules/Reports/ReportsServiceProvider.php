<?php

declare(strict_types=1);

namespace App\Modules\Reports;

use Illuminate\Support\ServiceProvider;

final class ReportsServiceProvider extends ServiceProvider
{
    public function boot(): void
    {
        $this->loadRoutesFrom(__DIR__.'/Routes/api.php');
    }
}
