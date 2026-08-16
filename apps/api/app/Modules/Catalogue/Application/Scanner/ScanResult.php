<?php

declare(strict_types=1);

namespace App\Modules\Catalogue\Application\Scanner;

final readonly class ScanResult
{
    private function __construct(
        public string $outcome,
        public string $normalizedValue,
        public ?string $variantId,
        public ?WeightedEan $weightedEan,
    ) {}

    public static function found(string $normalizedValue, string $variantId, ?WeightedEan $weightedEan = null): self
    {
        return new self('found', $normalizedValue, $variantId, $weightedEan);
    }

    public static function unknown(string $normalizedValue, ?WeightedEan $weightedEan = null): self
    {
        return new self('unknown', $normalizedValue, null, $weightedEan);
    }
}
