<?php

declare(strict_types=1);

namespace App\Modules\Fiscal;

use App\Modules\Fiscal\Application\Contracts\FiscalSubmissionQueue;
use App\Modules\Fiscal\Application\Contracts\FiscalGateway;
use App\Modules\Fiscal\Application\Contracts\FiscalSubmissionReader;
use App\Modules\Fiscal\Application\Contracts\FiscalSubmissionProcessor;
use App\Modules\Fiscal\Application\DatabaseFiscalSubmissionReader;
use App\Modules\Fiscal\Application\DatabaseFiscalSubmissionProcessor;
use App\Modules\Fiscal\Application\DatabaseFiscalSubmissionQueue;
use App\Modules\Fiscal\Application\UnconfiguredFiscalGateway;
use Illuminate\Support\ServiceProvider;

final class FiscalServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->scoped(FiscalGateway::class, UnconfiguredFiscalGateway::class);
        $this->app->scoped(FiscalSubmissionReader::class, DatabaseFiscalSubmissionReader::class);
        $this->app->scoped(FiscalSubmissionQueue::class, DatabaseFiscalSubmissionQueue::class);
        $this->app->scoped(FiscalSubmissionProcessor::class, DatabaseFiscalSubmissionProcessor::class);
    }

    public function boot(): void
    {
        $this->loadMigrationsFrom(__DIR__.'/Database/Migrations');
    }
}
