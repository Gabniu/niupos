<?php

declare(strict_types=1);

namespace App\Modules\Identity\Application\Middleware;

use App\Modules\Identity\Application\Contracts\ApiSessionManager;
use Closure;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

final readonly class AuthenticateApiSession
{
    public function __construct(private ApiSessionManager $sessions) {}

    public function handle(Request $request, Closure $next): Response
    {
        $resolved = $this->sessions->resolve($request->bearerToken() ?? '');

        if ($resolved === null) {
            return new JsonResponse([
                'error' => [
                    'code' => 'AUTH_UNAUTHENTICATED',
                    'message' => 'Authentication is required.',
                ],
            ], Response::HTTP_UNAUTHORIZED);
        }

        $request->setUserResolver(fn () => $resolved->user);
        $request->attributes->set('iam_session_id', $resolved->id);
        $request->attributes->set('iam_mfa_elevated_until', $resolved->mfaElevatedUntil);

        return $next($request);
    }
}
