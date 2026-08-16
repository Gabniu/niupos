<?php

declare(strict_types=1);

namespace App\Modules\Payments\Application\Http;

use App\Modules\Payments\Application\Contracts\PaymentProcessor;
use App\Modules\Payments\Application\Data\PaymentResult;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use InvalidArgumentException;
use RuntimeException;
use Symfony\Component\HttpFoundation\Response;

final readonly class PaymentOperationsController
{
    public function __construct(private PaymentProcessor $payments) {}

    public function initiate(Request $request): JsonResponse
    {
        $data = $request->validate([
            'sale_id' => ['required', 'uuid'],
            'method' => ['required', Rule::in(['cash', 'mpesa'])],
            'amount_minor' => ['required', 'integer', 'min:1'],
            'currency_code' => ['required', 'string', 'size:3', 'regex:/^[A-Za-z]{3}$/'],
            'provider_metadata' => ['sometimes', 'array:customer_reference,request_reference'],
            'provider_metadata.customer_reference' => ['sometimes', 'string', 'max:128', 'regex:/\S/'],
            'provider_metadata.request_reference' => ['sometimes', 'string', 'max:128', 'regex:/\S/'],
        ]);
        $idempotencyKey = $request->header('Idempotency-Key');
        if (! is_string($idempotencyKey) || trim($idempotencyKey) === '' || strlen($idempotencyKey) > 128) {
            return $this->invalid('A valid Idempotency-Key header is required.');
        }

        try {
            $result = $this->payments->initiate(
                $data['sale_id'], $data['method'], $data['amount_minor'], strtoupper($data['currency_code']),
                (string) $request->user()->getAuthIdentifier(), trim($idempotencyKey), $data['provider_metadata'] ?? [],
            );
        } catch (InvalidArgumentException) {
            return $this->invalid('The payment request is invalid.');
        } catch (ModelNotFoundException) {
            return $this->failure(Response::HTTP_NOT_FOUND, 'PAYMENT_UNAVAILABLE', 'The payment resource is unavailable.');
        } catch (RuntimeException) {
            return $this->failure(Response::HTTP_CONFLICT, 'PAYMENT_CONFLICT', 'The payment operation could not be completed.');
        }

        return $this->result($result, Response::HTTP_CREATED);
    }

    public function applyProviderResult(Request $request, string $attempt): JsonResponse
    {
        $data = $request->validate([
            'status' => ['required', Rule::in(['succeeded', 'failed'])],
            'provider_reference' => ['required', 'string', 'max:128', 'regex:/\S/'],
            'result_fingerprint' => ['required', 'string', 'regex:/^[a-fA-F0-9]{64}$/'],
        ]);

        try {
            $result = $this->payments->applyProviderResult(
                $attempt, $data['status'], trim($data['provider_reference']), strtolower($data['result_fingerprint']),
            );
        } catch (InvalidArgumentException) {
            return $this->invalid('The provider result is invalid.');
        } catch (ModelNotFoundException) {
            return $this->failure(Response::HTTP_NOT_FOUND, 'PAYMENT_UNAVAILABLE', 'The payment resource is unavailable.');
        } catch (RuntimeException) {
            return $this->failure(Response::HTTP_CONFLICT, 'PAYMENT_CONFLICT', 'The payment operation could not be completed.');
        }

        return $this->result($result);
    }

    private function result(PaymentResult $result, int $status = Response::HTTP_OK): JsonResponse
    {
        return new JsonResponse(['data' => [
            'attempt_id' => $result->attemptId,
            'sale_id' => $result->saleId,
            'method' => $result->method,
            'status' => $result->status,
            'amount_minor' => $result->amountMinor,
            'currency_code' => $result->currencyCode,
            'provider_reference' => $result->providerReference,
        ]], $status);
    }

    private function invalid(string $message): JsonResponse
    {
        return $this->failure(Response::HTTP_UNPROCESSABLE_ENTITY, 'PAYMENT_INVALID', $message);
    }

    private function failure(int $status, string $code, string $message): JsonResponse
    {
        return new JsonResponse(['error' => ['code' => $code, 'message' => $message]], $status);
    }
}
