<?php

declare(strict_types=1);

namespace App\Modules\Search;

use App\Modules\Search\Application\Contracts\CatalogueSearchRebuilder;
use App\Modules\Search\Application\Contracts\SearchProjection;
use App\Modules\Search\Application\DatabaseCatalogueSearchRebuilder;
use App\Modules\Search\Application\DatabaseSearchProjection;
use Illuminate\Support\ServiceProvider;

final class SearchServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->scoped(SearchProjection::class, DatabaseSearchProjection::class);
        $this->app->scoped(CatalogueSearchRebuilder::class, DatabaseCatalogueSearchRebuilder::class);
    }

    public function boot(): void
    {
        $this->loadMigrationsFrom(__DIR__.'/Database/Migrations');
    }
}
