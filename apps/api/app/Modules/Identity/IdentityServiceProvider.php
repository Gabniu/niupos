<?php

declare(strict_types=1);

namespace App\Modules\Identity;

use App\Modules\Identity\Application\Contracts\PermissionAuthorizer;
use App\Modules\Identity\Application\Contracts\ApiSessionManager;
use App\Modules\Identity\Application\Contracts\TenantIamAdministration;
use App\Modules\Identity\Application\Contracts\TenantOwnerBootstrap;
use App\Modules\Identity\Application\Contracts\OwnerMembershipProvisioner;
use App\Modules\Identity\Application\DatabaseTenantOwnerBootstrap;
use App\Modules\Identity\Application\DatabaseOwnerMembershipProvisioner;
use App\Modules\Identity\Application\DatabaseTenantIamAdministration;
use App\Modules\Identity\Infrastructure\DatabaseApiSessionManager;
use App\Modules\Identity\Infrastructure\DatabaseTenantAccessAuthorizer;
use App\Modules\Identity\Infrastructure\DatabasePermissionAuthorizer;
use App\Modules\Tenancy\Application\Contracts\TenantAccessAuthorizer;
use Illuminate\Support\ServiceProvider;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use App\Modules\Identity\Application\Console\BootstrapTenantOwnerCommand;
use App\Modules\Identity\Application\Console\CreateUserCommand;
use App\Modules\Identity\Application\Contracts\TotpManager;
use App\Modules\Identity\Application\Contracts\FederatedIdentityResolver;
use App\Modules\Identity\Application\Contracts\OidcDiscoveryClient;
use App\Modules\Identity\Application\Contracts\OidcAuthorizationService;
use App\Modules\Identity\Application\Contracts\OidcCallbackService;
use App\Modules\Identity\Application\Contracts\OidcTokenClient;
use App\Modules\Identity\Application\Contracts\OidcIdentityVerifier;
use App\Modules\Identity\Application\Contracts\FederatedIdentityMapper;
use App\Modules\Identity\Infrastructure\DatabaseTotpManager;
use App\Modules\Identity\Infrastructure\FailClosedFederatedIdentityResolver;
use App\Modules\Identity\Infrastructure\HttpOidcDiscoveryClient;
use App\Modules\Identity\Infrastructure\DatabaseOidcAuthorizationService;
use App\Modules\Identity\Infrastructure\DatabaseOidcCallbackService;
use App\Modules\Identity\Infrastructure\HttpOidcTokenClient;
use App\Modules\Identity\Infrastructure\LcobucciOidcIdentityVerifier;
use App\Modules\Identity\Infrastructure\DatabaseFederatedIdentityMapper;

final class IdentityServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->bind(TenantAccessAuthorizer::class, DatabaseTenantAccessAuthorizer::class);
        $this->app->bind(PermissionAuthorizer::class, DatabasePermissionAuthorizer::class);
        $this->app->bind(ApiSessionManager::class, DatabaseApiSessionManager::class);
        $this->app->bind(TenantIamAdministration::class, DatabaseTenantIamAdministration::class);
        $this->app->bind(TenantOwnerBootstrap::class, DatabaseTenantOwnerBootstrap::class);
        $this->app->bind(OwnerMembershipProvisioner::class, DatabaseOwnerMembershipProvisioner::class);
        $this->app->bind(TotpManager::class, DatabaseTotpManager::class);
        $this->app->bind(FederatedIdentityResolver::class, FailClosedFederatedIdentityResolver::class);
        $this->app->singleton(OidcDiscoveryClient::class, HttpOidcDiscoveryClient::class);
        $this->app->scoped(OidcAuthorizationService::class, DatabaseOidcAuthorizationService::class);
        $this->app->scoped(OidcCallbackService::class, DatabaseOidcCallbackService::class);
        $this->app->singleton(OidcTokenClient::class, HttpOidcTokenClient::class);
        $this->app->singleton(OidcIdentityVerifier::class, LcobucciOidcIdentityVerifier::class);
        $this->app->scoped(FederatedIdentityMapper::class, DatabaseFederatedIdentityMapper::class);
    }

    public function boot(): void
    {
        $this->loadMigrationsFrom(__DIR__.'/Database/Migrations');
        $this->loadRoutesFrom(__DIR__.'/Routes/api.php');
        if ($this->app->runningInConsole()) {
            $this->commands([BootstrapTenantOwnerCommand::class, CreateUserCommand::class]);
        }
        RateLimiter::for('iam-login', fn (Request $request): Limit => Limit::perMinute(5)->by(
            hash('sha256', mb_strtolower((string) $request->input('email'))).'|'.$request->ip(),
        ));
        RateLimiter::for('iam-mfa', fn (Request $request): Limit => Limit::perMinute(5)->by(
            (string) $request->attributes->get('iam_session_id').'|'.$request->ip(),
        ));
    }
}
