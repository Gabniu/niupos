<?php

declare(strict_types=1);

namespace App\Modules\Search;

use App\Modules\Search\Application\Contracts\SearchProjection;
use App\Modules\Search\Application\DatabaseSearchProjection;
use Illuminate\Support\ServiceProvider;

final class SearchServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->scoped(SearchProjection::class, DatabaseSearchProjection::class);
    }

    public function boot(): void
    {
        $this->loadMigrationsFrom(__DIR__.'/Database/Migrations');
    }
}
