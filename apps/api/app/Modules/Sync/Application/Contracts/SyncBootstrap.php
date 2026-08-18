<?php

declare(strict_types=1);

namespace App\Modules\Sync\Application\Contracts;

interface SyncBootstrap
{
    /** @return array<string, mixed> */
    /** @param array{section:string,collection:string,after_id?:string,limit?:int,snapshot_cursor?:int}|null $page */
    public function snapshot(string $devicePublicId, ?array $page = null): array;
}
