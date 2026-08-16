<?php

declare(strict_types=1);

namespace App\Modules\Onboarding\Application\Http;

use App\Modules\Onboarding\Application\Contracts\OnboardingDraftManager;
use App\Modules\Onboarding\Application\OnboardingDraftView;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;
use Throwable;

final readonly class OnboardingDraftController
{
    public function __construct(private OnboardingDraftManager $drafts) {}

    public function show(Request $request): JsonResponse
    {
        $userId = $this->userId($request);
        $draft = $this->drafts->find($userId);

        return new JsonResponse(['data' => $draft?->toArray()]);
    }

    public function save(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'channelSelection' => ['sometimes', 'nullable', 'string', 'in:pos,web,mobile,web_mobile'],
            'industryProfile' => ['sometimes', 'nullable', 'string', 'max:64'],
            'answers' => ['sometimes', 'array'],
            'currentStep' => ['sometimes', 'string', 'max:64'],
            'revision' => ['required', 'integer', 'min:0'],
        ]);

        $key = $request->header('Idempotency-Key');
        if (! is_string($key) || trim($key) === '') {
            return new JsonResponse(['error' => [
                'code' => 'ONBOARDING_IDEMPOTENCY_REQUIRED',
                'message' => 'An Idempotency-Key is required.',
            ]], Response::HTTP_UNPROCESSABLE_ENTITY);
        }

        try {
            $draft = $this->drafts->save($this->userId($request), $validated, (int) $validated['revision'], $key);
        } catch (Throwable $exception) {
            $status = str_contains($exception->getMessage(), 'changed')
                ? Response::HTTP_CONFLICT
                : Response::HTTP_UNPROCESSABLE_ENTITY;

            return new JsonResponse(['error' => [
                'code' => $status === Response::HTTP_CONFLICT ? 'ONBOARDING_REVISION_CONFLICT' : 'ONBOARDING_INVALID_DRAFT',
                'message' => $exception->getMessage(),
            ]], $status);
        }

        return new JsonResponse(['data' => $draft->toArray()]);
    }

    public function complete(Request $request): JsonResponse
    {
        $validated = $request->validate(['revision' => ['required', 'integer', 'min:0']]);
        $key = $request->header('Idempotency-Key');
        if (! is_string($key) || trim($key) === '') {
            return new JsonResponse(['error' => [
                'code' => 'ONBOARDING_IDEMPOTENCY_REQUIRED',
                'message' => 'An Idempotency-Key is required.',
            ]], Response::HTTP_UNPROCESSABLE_ENTITY);
        }

        try {
            $draft = $this->drafts->completeOrganization($this->userId($request), (int) $validated['revision'], $key);
        } catch (Throwable $exception) {
            $status = str_contains($exception->getMessage(), 'changed')
                ? Response::HTTP_CONFLICT
                : Response::HTTP_UNPROCESSABLE_ENTITY;

            return new JsonResponse(['error' => [
                'code' => $status === Response::HTTP_CONFLICT ? 'ONBOARDING_REVISION_CONFLICT' : 'ONBOARDING_COMPLETION_INVALID',
                'message' => $exception->getMessage(),
            ]], $status);
        }

        return new JsonResponse(['data' => $draft->toArray()], Response::HTTP_CREATED);
    }

    public function completeLocations(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'companyName' => ['required', 'string', 'max:160'],
            'branchCode' => ['required', 'string', 'max:64'],
            'branchName' => ['required', 'string', 'max:160'],
            'warehouseCode' => ['required', 'string', 'max:64'],
            'warehouseName' => ['required', 'string', 'max:160'],
            'registerCode' => ['required', 'string', 'max:64'],
            'registerName' => ['required', 'string', 'max:160'],
            'revision' => ['required', 'integer', 'min:0'],
        ]);
        $key = $request->header('Idempotency-Key');
        if (! is_string($key) || trim($key) === '') {
            return new JsonResponse(['error' => [
                'code' => 'ONBOARDING_IDEMPOTENCY_REQUIRED',
                'message' => 'An Idempotency-Key is required.',
            ]], Response::HTTP_UNPROCESSABLE_ENTITY);
        }

        try {
            $draft = $this->drafts->completePosLocations(
                $this->userId($request),
                $validated,
                (int) $validated['revision'],
                $key,
            );
        } catch (Throwable $exception) {
            $status = str_contains($exception->getMessage(), 'changed')
                ? Response::HTTP_CONFLICT
                : Response::HTTP_UNPROCESSABLE_ENTITY;

            return new JsonResponse(['error' => [
                'code' => $status === Response::HTTP_CONFLICT ? 'ONBOARDING_REVISION_CONFLICT' : 'ONBOARDING_LOCATION_INVALID',
                'message' => $exception->getMessage(),
            ]], $status);
        }

        return new JsonResponse(['data' => $draft->toArray()], Response::HTTP_CREATED);
    }

    private function userId(Request $request): string
    {
        $user = $request->user();
        $id = $user?->getAuthIdentifier();
        abort_unless(is_string($id) && $id !== '', Response::HTTP_UNAUTHORIZED);

        return $id;
    }
}
