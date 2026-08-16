<?php

declare(strict_types=1);

namespace App\Modules\Identity\Application;

final readonly class OwnerMembershipRecord
{
    public function __construct(
        public string $membershipId,
        public string $tenantId,
        public string $userId,
    ) {}
}
