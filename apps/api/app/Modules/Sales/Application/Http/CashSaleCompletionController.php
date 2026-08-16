<?php

declare(strict_types=1);

namespace App\Modules\Sales\Application\Http;

use App\Application\Contracts\CashSaleCompletion;
use DateTimeImmutable;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Symfony\Component\HttpFoundation\Response;

final readonly class CashSaleCompletionController
{
    public function __construct(private CashSaleCompletion $completion) {}

    public function __invoke(Request $request, string $sale): JsonResponse
    {
        $data = Validator::make(
            [...$request->all(), 'idempotency_key' => $request->header('Idempotency-Key')],
            [
                'completed_at' => ['required', 'date_format:Y-m-d\\TH:i:sP'],
                'idempotency_key' => ['required', 'string', 'min:1', 'max:128', 'regex:/^[\x21-\x7E]+$/'],
            ],
        )->validate();

        $completed = $this->completion->complete(
            $sale,
            (string) $request->user()->getAuthIdentifier(),
            $data['idempotency_key'],
            new DateTimeImmutable($data['completed_at']),
        );

        return new JsonResponse(['data' => [
            'sale_id' => $completed->saleId,
            'payment_attempt_id' => $completed->paymentAttemptId,
            'cash_movement_id' => $completed->cashMovementId,
            'receipt_id' => $completed->receiptId,
            'receipt_number' => $completed->receiptNumber,
            'amount_minor' => $completed->amountMinor,
            'currency_code' => $completed->currencyCode,
        ]], Response::HTTP_CREATED);
    }
}
