<?php

declare(strict_types=1);

namespace App\Modules\Identity\Application;

use App\Modules\Identity\Domain\User;
use DateTimeImmutable;

final readonly class AuthenticatedApiSession
{
    public function __construct(
        public string $id,
        public User $user,
        public ?DateTimeImmutable $mfaElevatedUntil,
    ) {}
}
