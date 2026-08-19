<?php

declare(strict_types=1);

namespace App\Modules\Fiscal;

use App\Modules\Fiscal\Application\Contracts\FiscalSubmissionQueue;
use App\Modules\Fiscal\Application\DatabaseFiscalSubmissionQueue;
use Illuminate\Support\ServiceProvider;

final class FiscalServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->scoped(FiscalSubmissionQueue::class, DatabaseFiscalSubmissionQueue::class);
    }

    public function boot(): void
    {
        $this->loadMigrationsFrom(__DIR__.'/Database/Migrations');
    }
}
