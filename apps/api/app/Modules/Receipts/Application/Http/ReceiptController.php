<?php

declare(strict_types=1);

namespace App\Modules\Receipts\Application\Http;

use App\Modules\Receipts\Application\Contracts\ReceiptDeliveryEvidence;
use App\Modules\Receipts\Application\Contracts\ReceiptReader;
use DateTimeImmutable;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use RuntimeException;

final readonly class ReceiptController
{
    public function __construct(private ReceiptReader $receipts, private ReceiptDeliveryEvidence $delivery) {}

    public function show(Request $request, string $receipt): JsonResponse
    {
        validator(['receipt' => $receipt], ['receipt' => ['required', 'uuid']])->validate();
        $view = $this->receipts->find($receipt);
        abort_if($view === null, 404, 'Receipt not found.');

        return new JsonResponse(['data' => [
            'id' => $view->id, 'sale_id' => $view->saleId, 'shift_id' => $view->shiftId,
            'register_id' => $view->registerId, 'seller_id' => $view->sellerId,
            'receipt_number' => $view->receiptNumber, 'currency_code' => $view->currencyCode,
            'net_minor' => $view->netMinor, 'tax_minor' => $view->taxMinor, 'gross_minor' => $view->grossMinor,
            'sale_finalized_at' => $view->saleFinalizedAt, 'issued_at' => $view->issuedAt, 'lines' => $view->lines,
        ]]);
    }

    public function recordDelivery(Request $request, string $receipt): JsonResponse
    {
        validator(['receipt' => $receipt], ['receipt' => ['required', 'uuid']])->validate();
        $allowed = ['channel', 'outcome', 'attempted_at', 'error_code'];
        if (array_diff(array_keys($request->all()), $allowed) !== []) {
            return new JsonResponse(['message' => 'The given data was invalid.', 'errors' => ['payload' => ['Unsupported fields are not accepted.']]], 422);
        }
        $data = $request->validate([
            'channel' => ['required', Rule::in(['printer', 'email', 'sms'])],
            'outcome' => ['required', Rule::in(['pending', 'succeeded', 'failed'])],
            'attempted_at' => ['required', 'date'],
            'error_code' => ['nullable', 'string', 'max:64', 'regex:/^[A-Za-z0-9_.-]+$/', 'required_if:outcome,failed', 'prohibited_unless:outcome,failed'],
        ]);
        try {
            $id = $this->delivery->record($receipt, $data['channel'], $data['outcome'], new DateTimeImmutable($data['attempted_at']), $data['error_code'] ?? null);
        } catch (RuntimeException) {
            abort(404, 'Receipt not found.');
        }

        return new JsonResponse(['data' => ['id' => $id, 'receipt_id' => $receipt, 'channel' => $data['channel'], 'outcome' => $data['outcome'], 'attempted_at' => (new DateTimeImmutable($data['attempted_at']))->format(DATE_ATOM), 'error_code' => $data['error_code'] ?? null]], 201);
    }
}
