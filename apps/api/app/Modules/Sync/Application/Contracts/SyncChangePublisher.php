<?php

declare(strict_types=1);

namespace App\Modules\Sync\Application\Contracts;

use App\Modules\Sync\Application\Data\SyncChange;

interface SyncChangePublisher
{
    /** @param array<string, mixed> $payload */
    public function publishChange(string $entityType, string $entityId, string $operation, array $payload): SyncChange;
}
