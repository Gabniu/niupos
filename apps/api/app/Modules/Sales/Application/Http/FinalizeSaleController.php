<?php

declare(strict_types=1);

namespace App\Modules\Sales\Application\Http;

use App\Modules\Sales\Application\Contracts\SalesCheckout;
use DateTimeImmutable;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Symfony\Component\HttpFoundation\Response;

final readonly class FinalizeSaleController
{
    public function __construct(private SalesCheckout $checkout) {}

    public function __invoke(Request $request): JsonResponse
    {
        $data = Validator::make(
            [...$request->all(), 'idempotency_key' => $request->header('Idempotency-Key')],
            [
                'register_id' => ['required', 'uuid'],
                'warehouse_id' => ['required', 'uuid'],
                'price_book_id' => ['required', 'uuid'],
                'currency_code' => ['required', 'string', 'size:3', 'regex:/^[A-Z]{3}$/'],
                'lines' => ['required', 'array', 'min:1', 'max:100'],
                'lines.*.variant_id' => ['required', 'uuid'],
                'lines.*.quantity' => ['required', 'integer', 'min:1', 'max:2147483647'],
                'occurred_at' => ['required', 'date_format:Y-m-d\\TH:i:sP'],
                'idempotency_key' => ['required', 'string', 'min:1', 'max:128', 'regex:/^[\x21-\x7E]+$/'],
            ],
        )->validate();

        $sale = $this->checkout->finalize(
            $data['register_id'],
            (string) $request->user()->getAuthIdentifier(),
            $data['warehouse_id'],
            $data['price_book_id'],
            $data['currency_code'],
            $data['lines'],
            $data['idempotency_key'],
            new DateTimeImmutable($data['occurred_at']),
        );

        return new JsonResponse(['data' => [
            'sale_id' => $sale->saleId,
            'shift_id' => $sale->shiftId,
            'register_id' => $sale->registerId,
            'currency_code' => $sale->currencyCode,
            'net_minor' => $sale->netMinor,
            'tax_minor' => $sale->taxMinor,
            'gross_minor' => $sale->grossMinor,
            'line_count' => $sale->lineCount,
            'finalized_at' => $sale->finalizedAt->format(DATE_ATOM),
        ]], Response::HTTP_CREATED);
    }
}
