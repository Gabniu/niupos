<?php

declare(strict_types=1);

namespace App\Modules\Sync\Application\Contracts;

use App\Modules\Sync\Application\Data\SyncCommandEnvelope;
use App\Modules\Sync\Application\Data\SyncCommandOutcome;

interface SyncCommandHandler
{
    public function handle(string $tenantId, string $deviceId, SyncCommandEnvelope $command): SyncCommandOutcome;
}
