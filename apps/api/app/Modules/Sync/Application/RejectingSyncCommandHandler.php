<?php

declare(strict_types=1);

namespace App\Modules\Sync\Application;

use App\Modules\Sync\Application\Contracts\SyncCommandHandler;
use App\Modules\Sync\Application\Data\SyncCommandEnvelope;
use App\Modules\Sync\Application\Data\SyncCommandOutcome;

final class RejectingSyncCommandHandler implements SyncCommandHandler
{
    public function handle(string $tenantId, string $deviceId, SyncCommandEnvelope $command): SyncCommandOutcome
    {
        return SyncCommandOutcome::rejected('unsupported_command_type', "No handler is registered for {$command->type}.");
    }
}
