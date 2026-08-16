<?php

declare(strict_types=1);

namespace App\Modules\Audit;

use App\Modules\Audit\Application\Contracts\SecurityAuditRecorder;
use App\Modules\Audit\Infrastructure\DatabaseSecurityAuditRecorder;
use Illuminate\Support\ServiceProvider;

final class AuditServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->bind(SecurityAuditRecorder::class, DatabaseSecurityAuditRecorder::class);
    }

    public function boot(): void
    {
        $this->loadMigrationsFrom(__DIR__.'/Database/Migrations');
    }
}
