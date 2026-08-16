<?php

declare(strict_types=1);

namespace App\Modules\Tenancy\Application;

use Illuminate\Support\Str;

final readonly class TenantEventEnvelope
{
    public function __construct(private TenantContext $context) {}

    /** @param array<string, mixed> $payload */
    public function wrap(string $eventType, array $payload): array
    {
        return [
            'event_id' => (string) Str::uuid(),
            'event_type' => $eventType,
            'tenant_id' => (string) $this->context->id(),
            'payload' => $payload,
        ];
    }
}
