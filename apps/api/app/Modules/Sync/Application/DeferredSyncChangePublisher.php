<?php

declare(strict_types=1);

namespace App\Modules\Sync\Application;

use App\Modules\Sync\Application\Contracts\SyncChangePublisher;
use App\Modules\Sync\Application\Contracts\SyncProtocol;
use App\Modules\Sync\Application\Data\SyncChange;
use Illuminate\Contracts\Container\Container;

/**
 * Resolves the full sync protocol only when a mutation emits a change.
 *
 * Catalogue and pricing are used by SalesCheckout, which is itself the
 * command handler for SyncProtocol. Keeping this lookup lazy prevents that
 * construction cycle while preserving transactional change publication.
 */
final readonly class DeferredSyncChangePublisher implements SyncChangePublisher
{
    public function __construct(private Container $container) {}

    public function publishChange(string $entityType, string $entityId, string $operation, array $payload): SyncChange
    {
        return $this->container->make(SyncProtocol::class)->publishChange($entityType, $entityId, $operation, $payload);
    }
}
