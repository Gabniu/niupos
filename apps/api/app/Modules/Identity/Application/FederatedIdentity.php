<?php

declare(strict_types=1);

namespace App\Modules\Identity\Application;

use DateTimeImmutable;

final readonly class FederatedIdentity
{
    /** @param array<string, mixed> $claims */
    public function __construct(
        public string $issuer,
        public string $subject,
        public ?string $email,
        public ?string $name,
        public DateTimeImmutable $expiresAt,
        public array $claims = [],
    ) {}
}
