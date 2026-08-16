<?php

declare(strict_types=1);

namespace App\Modules\Sync\Application\Contracts;

interface SyncBootstrap
{
    /** @return array<string, mixed> */
    public function snapshot(string $devicePublicId): array;
}
