<?php

declare(strict_types=1);

namespace App\Modules\Identity\Domain;

use InvalidArgumentException;

final readonly class PermissionKey
{
    private const PATTERN = '/^[a-z][a-z0-9]*(?:\.[a-z][a-z0-9]*)+$/';

    public function __construct(private string $value)
    {
        if (preg_match(self::PATTERN, $value) !== 1) {
            throw new InvalidArgumentException('Permission keys must use lowercase dotted notation.');
        }
    }

    public function __toString(): string
    {
        return $this->value;
    }
}
