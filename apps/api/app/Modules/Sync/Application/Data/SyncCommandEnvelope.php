<?php

declare(strict_types=1);

namespace App\Modules\Sync\Application\Data;

final readonly class SyncCommandEnvelope
{
    /** @param array<string, mixed> $payload */
    public function __construct(
        public string $version,
        public string $commandId,
        public string $type,
        public string $occurredAt,
        public array $payload,
    ) {}
}
