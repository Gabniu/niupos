<?php

declare(strict_types=1);

namespace App\Modules\Sync\Application\Data;

final readonly class SyncCommandReceipt
{
    public function __construct(
        public string $commandId,
        public string $status,
        public int $attempts,
        public ?string $resultCode,
        public ?string $resultMessage,
    ) {}
}
