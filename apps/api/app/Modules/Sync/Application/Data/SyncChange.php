<?php

declare(strict_types=1);

namespace App\Modules\Sync\Application\Data;

final readonly class SyncChange
{
    /** @param array<string, mixed> $payload */
    public function __construct(
        public int $cursor,
        public string $entityType,
        public string $entityId,
        public string $operation,
        public array $payload,
        public string $occurredAt,
    ) {}
}
