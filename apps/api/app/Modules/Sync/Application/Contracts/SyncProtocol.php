<?php

declare(strict_types=1);

namespace App\Modules\Sync\Application\Contracts;

use App\Modules\Sync\Application\Data\SyncChange;
use App\Modules\Sync\Application\Data\SyncChangePage;
use App\Modules\Sync\Application\Data\SyncCommandEnvelope;
use App\Modules\Sync\Application\Data\SyncCommandReceipt;

interface SyncProtocol
{
    /** @param array<string, mixed> $payload */
    public function publishChange(string $entityType, string $entityId, string $operation, array $payload): SyncChange;

    public function pull(string $devicePublicId, int $afterCursor, int $limit = 100): SyncChangePage;

    public function submit(string $devicePublicId, SyncCommandEnvelope $command): SyncCommandReceipt;

    public function retry(string $devicePublicId, string $commandId): SyncCommandReceipt;
}
