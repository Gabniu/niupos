<?php

declare(strict_types=1);

namespace App\Modules\Identity\Application\Middleware;

use Closure;
use DateTimeImmutable;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Date;
use Symfony\Component\HttpFoundation\Response;

final readonly class RequireMfaElevation
{
    public function handle(Request $request, Closure $next): Response
    {
        $elevatedUntil = $request->attributes->get('iam_mfa_elevated_until');
        if (! $elevatedUntil instanceof DateTimeImmutable || $elevatedUntil <= Date::now()) {
            return new JsonResponse(['error' => [
                'code' => 'MFA_ELEVATION_REQUIRED',
                'message' => 'A recent MFA verification is required.',
            ]], Response::HTTP_FORBIDDEN);
        }

        return $next($request);
    }
}
