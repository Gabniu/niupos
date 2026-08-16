<?php

declare(strict_types=1);

namespace App\Modules\Tenancy\Domain;

use InvalidArgumentException;

final readonly class TenantId
{
    private const UUID_PATTERN = '/^[0-9a-f]{8}-[0-9a-f]{4}-[1-8][0-9a-f]{3}-[89ab][0-9a-f]{3}-[0-9a-f]{12}$/i';

    private function __construct(public string $value) {}

    public static function fromString(string $value): self
    {
        if (preg_match(self::UUID_PATTERN, $value) !== 1) {
            throw new InvalidArgumentException('Tenant ID must be a valid UUID.');
        }

        return new self(strtolower($value));
    }

    public function equals(self $other): bool
    {
        return $this->value === $other->value;
    }

    public function __toString(): string
    {
        return $this->value;
    }
}
