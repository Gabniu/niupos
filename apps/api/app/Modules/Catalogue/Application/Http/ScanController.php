<?php

declare(strict_types=1);

namespace App\Modules\Catalogue\Application\Http;

use App\Modules\Catalogue\Application\Contracts\ScannerResolver;
use App\Modules\Catalogue\Application\Scanner\ScannerInputMode;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

final readonly class ScanController
{
    public function __construct(private ScannerResolver $scanner) {}

    public function __invoke(Request $request): JsonResponse
    {
        $data = $request->validate([
            'value' => ['required', 'string', 'max:128', 'regex:/\S/'],
            'mode' => ['required', Rule::enum(ScannerInputMode::class)],
        ]);
        $result = $this->scanner->resolve($data['value'], ScannerInputMode::from($data['mode']));

        return new JsonResponse(['data' => [
            'outcome' => $result->outcome,
            'normalized_value' => $result->normalizedValue,
            'variant_id' => $result->variantId,
            'weighted_ean' => $result->weightedEan === null ? null : [
                'prefix' => $result->weightedEan->prefix,
                'item_reference' => $result->weightedEan->itemReference,
                'weight_grams' => $result->weightedEan->weightGrams,
            ],
        ]]);
    }
}
