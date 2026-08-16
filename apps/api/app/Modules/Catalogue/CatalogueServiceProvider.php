<?php

declare(strict_types=1);

namespace App\Modules\Catalogue;

use App\Modules\Catalogue\Application\Contracts\ActiveVariantLookup;
use App\Modules\Catalogue\Application\Contracts\CatalogueManager;
use App\Modules\Catalogue\Application\Contracts\ScannerResolver;
use App\Modules\Catalogue\Application\DatabaseCatalogueManager;
use App\Modules\Catalogue\Application\Scanner\DatabaseScannerResolver;
use App\Modules\Catalogue\Infrastructure\DatabaseActiveVariantLookup;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;

final class CatalogueServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->scoped(CatalogueManager::class, DatabaseCatalogueManager::class);
        $this->app->scoped(ActiveVariantLookup::class, DatabaseActiveVariantLookup::class);
        $this->app->scoped(ScannerResolver::class, DatabaseScannerResolver::class);
    }

    public function boot(): void
    {
        $this->loadMigrationsFrom(__DIR__.'/Database/Migrations');
        $this->loadRoutesFrom(__DIR__.'/Routes/scan.php');
        RateLimiter::for('catalogue-scan', fn (Request $request): Limit => Limit::perMinute(120)->by(
            (string) $request->attributes->get('iam_session_id').'|'.$request->ip(),
        ));
    }
}
