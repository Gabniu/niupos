<?php

declare(strict_types=1);

namespace App\Modules\Fiscal\Application\Data;

final readonly class FiscalSubmissionSummary
{
    /** @param array<string, int> $counts */
    public function __construct(
        public array $counts,
        public int $total,
        public ?string $oldestPendingAt,
        public ?string $nextRetryAt,
    ) {}
}
