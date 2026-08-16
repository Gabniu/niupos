<?php

declare(strict_types=1);

namespace App\Modules\Onboarding;

use App\Modules\Onboarding\Application\Contracts\OnboardingDraftManager;
use App\Modules\Onboarding\Application\Contracts\OnboardingProvisioningManager;
use App\Modules\Onboarding\Application\Contracts\OnboardingSetupTimelineReader;
use App\Modules\Onboarding\Application\Contracts\OnboardingProvisioningWorker;
use App\Modules\Onboarding\Application\Contracts\OnboardingSetupNotificationReader;
use App\Modules\Onboarding\Application\Contracts\OnboardingSetupNotificationWriter;
use App\Modules\Onboarding\Application\Contracts\OnboardingProvisioningExecutorRegistry;
use App\Modules\Onboarding\Application\Contracts\OnboardingNotificationDeliveryAdapter;
use App\Modules\Onboarding\Application\Contracts\OnboardingNotificationDeliveryDispatcher;
use App\Modules\Onboarding\Application\DatabaseOnboardingDraftManager;
use App\Modules\Onboarding\Application\DatabaseOnboardingProvisioningManager;
use App\Modules\Onboarding\Application\DatabaseOnboardingSetupTimelineReader;
use App\Modules\Onboarding\Application\DatabaseOnboardingProvisioningWorker;
use App\Modules\Onboarding\Application\DatabaseOnboardingSetupNotificationReader;
use App\Modules\Onboarding\Application\DatabaseOnboardingSetupNotificationWriter;
use App\Modules\Onboarding\Application\DatabaseOnboardingProvisioningExecutorRegistry;
use App\Modules\Onboarding\Application\DatabaseOnboardingNotificationDeliveryDispatcher;
use App\Modules\Onboarding\Application\ResendOnboardingNotificationDeliveryAdapter;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;

final class OnboardingServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->scoped(OnboardingDraftManager::class, DatabaseOnboardingDraftManager::class);
        $this->app->scoped(OnboardingProvisioningManager::class, DatabaseOnboardingProvisioningManager::class);
        $this->app->scoped(OnboardingSetupTimelineReader::class, DatabaseOnboardingSetupTimelineReader::class);
        $this->app->scoped(OnboardingProvisioningWorker::class, DatabaseOnboardingProvisioningWorker::class);
        $this->app->scoped(OnboardingSetupNotificationReader::class, DatabaseOnboardingSetupNotificationReader::class);
        $this->app->scoped(OnboardingSetupNotificationWriter::class, DatabaseOnboardingSetupNotificationWriter::class);
        $this->app->singleton(OnboardingProvisioningExecutorRegistry::class, DatabaseOnboardingProvisioningExecutorRegistry::class);
        $this->app->scoped(OnboardingNotificationDeliveryAdapter::class, ResendOnboardingNotificationDeliveryAdapter::class);
        $this->app->scoped(OnboardingNotificationDeliveryDispatcher::class, DatabaseOnboardingNotificationDeliveryDispatcher::class);
    }

    public function boot(): void
    {
        RateLimiter::for('onboarding-draft', fn (Request $request): Limit => Limit::perMinute(60)->by(
            (string) $request->attributes->get('iam_session_id').'|'.$request->ip(),
        ));
        $this->loadMigrationsFrom(__DIR__.'/Database/Migrations');
        $this->loadRoutesFrom(__DIR__.'/Routes/api.php');
    }
}
