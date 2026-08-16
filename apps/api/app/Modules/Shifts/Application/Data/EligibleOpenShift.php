<?php

declare(strict_types=1);

namespace App\Modules\Shifts\Application\Data;

use DateTimeImmutable;

final readonly class EligibleOpenShift
{
    public function __construct(
        public string $tenantId,
        public string $shiftId,
        public string $registerId,
        public string $actorUserId,
        public string $currency,
        public DateTimeImmutable $openedAt,
    ) {}
}
