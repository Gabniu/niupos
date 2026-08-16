<?php

declare(strict_types=1);

namespace App\Modules\Sync\Application\Data;

final readonly class SyncChangePage
{
    /** @param list<SyncChange> $changes */
    public function __construct(public string $version, public int $cursor, public array $changes, public bool $hasMore) {}
}
