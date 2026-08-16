<?php

declare(strict_types=1);

namespace App\Modules\Channels\Application\Http;

use App\Modules\Channels\Application\Contracts\ChannelRegistrationManager;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;
use Throwable;

final readonly class ChannelRegistrationController
{
    public function __construct(private ChannelRegistrationManager $registrations) {}

    public function index(): JsonResponse
    {
        return new JsonResponse(['data' => $this->registrations->registrations()->map(static fn ($registration): array => $registration->toArray())->values()->all()]);
    }

    public function create(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'channel' => ['required', 'string', 'in:web,mobile'],
            'displayName' => ['required', 'string', 'max:160'],
            'environment' => ['required', 'string', 'in:development,staging,production'],
            'configuration' => ['sometimes', 'array'],
            'redirectUris' => ['sometimes', 'array', 'max:20'],
            'redirectUris.*' => ['string', 'max:2048'],
        ]);
        $idempotencyKey = $request->header('Idempotency-Key');
        if (! is_string($idempotencyKey) || trim($idempotencyKey) === '') {
            return new JsonResponse(['error' => ['code' => 'CHANNEL_IDEMPOTENCY_REQUIRED', 'message' => 'An Idempotency-Key is required.']], Response::HTTP_UNPROCESSABLE_ENTITY);
        }

        try {
            $registration = $this->registrations->create(
                $validated['channel'],
                $validated['displayName'],
                $validated['environment'],
                $validated['configuration'] ?? [],
                $validated['redirectUris'] ?? [],
                $idempotencyKey,
            );
        } catch (Throwable $exception) {
            return new JsonResponse(['error' => ['code' => 'CHANNEL_REGISTRATION_INVALID', 'message' => $exception->getMessage()]], Response::HTTP_UNPROCESSABLE_ENTITY);
        }

        return new JsonResponse(['data' => $registration->toArray()], Response::HTTP_CREATED);
    }

    public function requestApproval(string $registrationId): JsonResponse
    {
        try {
            $registration = $this->registrations->requestApproval($registrationId);
        } catch (Throwable $exception) {
            return new JsonResponse(['error' => ['code' => 'CHANNEL_APPROVAL_INVALID', 'message' => $exception->getMessage()]], Response::HTTP_UNPROCESSABLE_ENTITY);
        }

        return new JsonResponse(['data' => $registration->toArray()]);
    }
}
