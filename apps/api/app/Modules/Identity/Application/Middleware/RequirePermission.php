<?php

declare(strict_types=1);

namespace App\Modules\Identity\Application\Middleware;

use App\Modules\Identity\Application\Contracts\PermissionAuthorizer;
use App\Modules\Identity\Domain\PermissionKey;
use Closure;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

final readonly class RequirePermission
{
    public function __construct(private PermissionAuthorizer $permissions) {}

    public function handle(Request $request, Closure $next, string $permission): Response
    {
        $actor = $request->user();

        if (! $actor instanceof Authenticatable
            || ! $this->permissions->allows($actor, new PermissionKey($permission))) {
            return new JsonResponse(['error' => [
                'code' => 'FORBIDDEN',
                'message' => 'The requested operation is not permitted.',
            ]], Response::HTTP_FORBIDDEN);
        }

        return $next($request);
    }
}
