<?php

declare(strict_types=1);

namespace App\Modules\Identity\Application\Http;

use App\Modules\Identity\Application\Contracts\TenantIamAdministration;
use App\Modules\Identity\Domain\MembershipStatus;
use App\Modules\Identity\Domain\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Symfony\Component\HttpFoundation\Response;

final readonly class TenantIamAdministrationController
{
    public function __construct(private TenantIamAdministration $administration) {}

    public function createRole(Request $request): JsonResponse
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'min:2', 'max:64', 'regex:/^[A-Za-z][A-Za-z0-9_-]+$/'],
            'description' => ['nullable', 'string', 'max:255'],
        ]);
        $role = $this->administration->createRole(
            $this->actor($request),
            $data['name'],
            $data['description'] ?? null,
        );

        return new JsonResponse(['data' => [
            'id' => $role->getKey(),
            'name' => $role->name,
            'description' => $role->description,
        ]], Response::HTTP_CREATED);
    }

    public function replaceRolePermissions(Request $request, string $roleId): JsonResponse
    {
        $data = $request->validate([
            'permissions' => ['required', 'array', 'max:256'],
            'permissions.*' => ['required', 'string', 'distinct', 'max:128', 'regex:/^[a-z][a-z0-9]*(?:\.[a-z][a-z0-9]*)+$/'],
        ]);
        $this->administration->replaceRolePermissions($this->actor($request), $roleId, $data['permissions']);

        return new JsonResponse(null, Response::HTTP_NO_CONTENT);
    }

    public function assignMembership(Request $request, string $userId): JsonResponse
    {
        $data = $request->validate([
            'role_id' => ['required', 'uuid'],
            'status' => ['required', Rule::enum(MembershipStatus::class)],
        ]);
        $membership = $this->administration->assignMembership(
            $this->actor($request),
            $userId,
            $data['role_id'],
            MembershipStatus::from($data['status']),
        );

        return new JsonResponse(['data' => [
            'id' => $membership->getKey(),
            'user_id' => $membership->user_id,
            'role_id' => $membership->role_id,
            'status' => $membership->status,
        ]]);
    }

    public function transferOwnership(Request $request, string $userId): JsonResponse
    {
        $this->administration->transferOwnership($this->actor($request), $userId);

        return new JsonResponse(null, Response::HTTP_NO_CONTENT);
    }

    private function actor(Request $request): User
    {
        $actor = $request->user();
        abort_unless($actor instanceof User, Response::HTTP_UNAUTHORIZED);

        return $actor;
    }
}
