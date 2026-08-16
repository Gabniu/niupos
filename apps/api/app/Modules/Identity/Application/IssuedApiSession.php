<?php

declare(strict_types=1);

namespace App\Modules\Identity\Application;

use DateTimeImmutable;

final readonly class IssuedApiSession
{
    public function __construct(
        public string $id,
        public string $token,
        public DateTimeImmutable $expiresAt,
    ) {}
}
