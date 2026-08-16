<?php

declare(strict_types=1);

namespace App\Modules\Sales\Application\Http;

use App\Modules\Pricing\Application\Contracts\CheckoutQuoteProvider;
use App\Modules\Tenancy\Application\TenantContext;
use DateTimeImmutable;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Symfony\Component\HttpFoundation\Response;

final readonly class SalesWorkspaceController
{
    public function __construct(private TenantContext $context, private CheckoutQuoteProvider $quotes) {}

    public function priceBooks(): JsonResponse
    {
        $books = DB::table('pricing_price_books')
            ->where('tenant_id', (string) $this->context->id())
            ->where('status', 'active')
            ->orderBy('name')
            ->get(['id', 'name', 'currency_code']);

        return new JsonResponse(['data' => $books->map(static fn (object $book): array => [
            'id' => (string) $book->id,
            'name' => (string) $book->name,
            'currencyCode' => (string) $book->currency_code,
        ])->values()->all()]);
    }

    public function quote(Request $request): JsonResponse
    {
        $data = Validator::make($request->all(), [
            'price_book_id' => ['required', 'uuid'],
            'currency_code' => ['required', 'string', 'size:3', 'regex:/^[A-Z]{3}$/'],
            'at' => ['required', 'date_format:Y-m-d\\TH:i:sP'],
            'lines' => ['required', 'array', 'min:1', 'max:100'],
            'lines.*.variant_id' => ['required', 'uuid'],
            'lines.*.quantity' => ['required', 'integer', 'min:1', 'max:2147483647'],
        ])->validate();

        $at = new DateTimeImmutable($data['at']);
        $lines = array_map(function (array $line) use ($data, $at): array {
            $quote = $this->quotes->quote($data['price_book_id'], $line['variant_id'], (int) $line['quantity'], $data['currency_code'], $at);

            return [
                'variantId' => $quote->variantId,
                'quantity' => $quote->quantity,
                'currencyCode' => $quote->currencyCode,
                'unitPriceMinor' => $quote->unitPriceMinor,
                'netMinor' => $quote->netMinor,
                'taxMinor' => $quote->taxMinor,
                'grossMinor' => $quote->grossMinor,
                'taxCode' => $quote->taxCode,
                'taxRateBasisPoints' => $quote->taxRateBasisPoints,
                'taxInclusive' => $quote->taxInclusive,
                'priceBookId' => $quote->priceBookId,
                'quotedAt' => $quote->quotedAt->format(DATE_ATOM),
            ];
        }, $data['lines']);

        return new JsonResponse(['data' => ['lines' => $lines]]);
    }
}
