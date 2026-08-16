<?php

declare(strict_types=1);

namespace App\Modules\Catalogue\Application\Scanner;

final readonly class WeightedEan
{
    public function __construct(
        public string $prefix,
        public string $itemReference,
        public int $weightGrams,
    ) {}

    public static function parse(string $normalizedValue): ?self
    {
        if (preg_match('/^(2[0-9])(\d{5})(\d{5})\d$/', $normalizedValue, $matches) !== 1) {
            return null;
        }

        return new self($matches[1], $matches[2], (int) $matches[3]);
    }
}
