<?php

declare(strict_types=1);

namespace App\Modules\Tenancy\Application;

use InvalidArgumentException;

final readonly class TenantCacheKey
{
    public function __construct(private TenantContext $context) {}

    public function for(string $key): string
    {
        $normalized = trim($key);

        if ($normalized === '' || preg_match('/\s/', $normalized) === 1) {
            throw new InvalidArgumentException('Cache key must be non-empty and contain no whitespace.');
        }

        return 'tenant:'.$this->context->id().':'.$normalized;
    }
}
