<?php

declare(strict_types=1);

namespace App\Modules\Audit\Application;

final readonly class SecurityAuditEvent
{
    /** @param array<string, scalar|null> $metadata */
    public function __construct(
        public string $type,
        public ?string $actorUserId,
        public array $metadata,
        public ?string $tenantId = null,
    ) {}
}
