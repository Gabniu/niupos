<?php

declare(strict_types=1);

namespace App\Modules\Tenancy;

use App\Modules\Tenancy\Application\TenantContext;
use App\Modules\Tenancy\Application\TenantScope;
use App\Modules\Tenancy\Application\Contracts\TenantCreator;
use App\Modules\Tenancy\Application\Contracts\OrganizationLocations;
use App\Modules\Tenancy\Application\Contracts\TenantAccessAuthorizer;
use App\Modules\Tenancy\Application\DatabaseTenantCreator;
use App\Modules\Tenancy\Application\DatabaseOrganizationLocations;
use App\Modules\Tenancy\Infrastructure\DenyAllTenantAccess;
use Illuminate\Support\ServiceProvider;

final class TenancyServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->scoped(TenantContext::class, fn (): TenantContext => new TenantContext);
        $this->app->scoped(TenantScope::class);
        $this->app->bind(TenantAccessAuthorizer::class, DenyAllTenantAccess::class);
        $this->app->bind(OrganizationLocations::class, DatabaseOrganizationLocations::class);
        $this->app->bind(TenantCreator::class, DatabaseTenantCreator::class);
    }

    public function boot(): void
    {
        $this->loadMigrationsFrom(__DIR__.'/Database/Migrations');
        $this->loadRoutesFrom(__DIR__.'/Routes/api.php');
    }
}
