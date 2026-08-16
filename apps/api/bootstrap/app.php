<?php

use App\Modules\Identity\Application\Middleware\AuthenticateApiSession;
use App\Modules\Identity\Application\Middleware\RequireMfaElevation;
use App\Modules\Identity\Application\Middleware\RequirePermission;
use App\Modules\Tenancy\Application\Middleware\ResolveTenantFromHeader;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        // Every deployed environment sits behind a TLS-terminating reverse
        // proxy (infra/reverse-proxy/), so the request reaching PHP is plain
        // HTTP and only the X-Forwarded-* headers carry the original scheme,
        // host and client address.
        //
        // Without this, Laravel trusts the connection it can see: url() and
        // route() emit http:// links, $request->secure() is false so a
        // secure-flagged session cookie is never sent back, and signed URLs
        // are generated over one scheme and validated against another, which
        // fails the signature check. The symptoms read as unrelated bugs.
        //
        // Trusting '*' is safe *here specifically* because the container
        // publishes to 127.0.0.1 only, so the proxy is the sole possible
        // source of a request and no client can forge these headers. If this
        // application is ever exposed directly, narrow this to the proxy's
        // address -- an untrusted client spoofing X-Forwarded-For would
        // otherwise defeat any rate limiting or IP logging.
        $middleware->trustProxies(at: '*');

        $middleware->alias([
            'tenant' => ResolveTenantFromHeader::class,
            'api.session' => AuthenticateApiSession::class,
            'mfa.elevated' => RequireMfaElevation::class,
            'permission' => RequirePermission::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->shouldRenderJsonWhen(
            fn (Request $request) => $request->is('api/*'),
        );
    })->create();
