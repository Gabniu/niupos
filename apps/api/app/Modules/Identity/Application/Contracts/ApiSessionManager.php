<?php

declare(strict_types=1);

namespace App\Modules\Identity\Application\Contracts;

use App\Modules\Identity\Application\IssuedApiSession;
use App\Modules\Identity\Application\AuthenticatedApiSession;
use App\Modules\Identity\Domain\User;
use DateInterval;
use DateTimeImmutable;

interface ApiSessionManager
{
    public function issue(User $user, ?DateInterval $lifetime = null): IssuedApiSession;

    public function resolve(string $token): ?AuthenticatedApiSession;

    public function revoke(string $sessionId, User $user): bool;

    public function revokeAll(User $user): int;

    public function elevate(string $sessionId, User $user, ?DateInterval $lifetime = null): ?DateTimeImmutable;
}
