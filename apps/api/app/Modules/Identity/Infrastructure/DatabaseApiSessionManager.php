<?php

declare(strict_types=1);

namespace App\Modules\Identity\Infrastructure;

use App\Modules\Identity\Application\Contracts\ApiSessionManager;
use App\Modules\Identity\Application\AuthenticatedApiSession;
use App\Modules\Identity\Application\IssuedApiSession;
use App\Modules\Identity\Domain\ApiSession;
use App\Modules\Identity\Domain\User;
use DateInterval;
use DateTimeImmutable;
use Illuminate\Support\Facades\Date;

final class DatabaseApiSessionManager implements ApiSessionManager
{
    private const DEFAULT_LIFETIME = 'PT8H';
    private const DEFAULT_MFA_ELEVATION_LIFETIME = 'PT5M';

    public function issue(User $user, ?DateInterval $lifetime = null): IssuedApiSession
    {
        $token = $this->generateToken();
        $expiresAt = Date::now()->add($lifetime ?? new DateInterval(self::DEFAULT_LIFETIME));
        $session = ApiSession::query()->create([
            'user_id' => $user->getKey(),
            'token_hash' => $this->hash($token),
            'expires_at' => $expiresAt,
        ]);

        return new IssuedApiSession(
            (string) $session->getKey(),
            $token,
            $expiresAt->toDateTimeImmutable(),
        );
    }

    public function resolve(string $token): ?AuthenticatedApiSession
    {
        if ($token === '') {
            return null;
        }

        $session = ApiSession::query()
            ->where('token_hash', $this->hash($token))
            ->whereNull('revoked_at')
            ->where('expires_at', '>', Date::now())
            ->first();

        if (! $session instanceof ApiSession) {
            return null;
        }

        $session->forceFill(['last_used_at' => Date::now()])->save();

        $user = User::query()->find($session->getAttribute('user_id'));

        return $user instanceof User
            ? new AuthenticatedApiSession(
                (string) $session->getKey(),
                $user,
                $session->mfa_elevated_until?->toDateTimeImmutable(),
            )
            : null;
    }

    public function revoke(string $sessionId, User $user): bool
    {
        return ApiSession::query()
            ->whereKey($sessionId)
            ->where('user_id', $user->getKey())
            ->whereNull('revoked_at')
            ->update(['revoked_at' => Date::now()]) === 1;
    }

    public function revokeAll(User $user): int
    {
        return ApiSession::query()
            ->where('user_id', $user->getKey())
            ->whereNull('revoked_at')
            ->update(['revoked_at' => Date::now()]);
    }

    public function elevate(string $sessionId, User $user, ?DateInterval $lifetime = null): ?DateTimeImmutable
    {
        $elevatedUntil = Date::now()->add($lifetime ?? new DateInterval(self::DEFAULT_MFA_ELEVATION_LIFETIME));
        $updated = ApiSession::query()
            ->whereKey($sessionId)
            ->where('user_id', $user->getKey())
            ->whereNull('revoked_at')
            ->where('expires_at', '>', $elevatedUntil)
            ->update(['mfa_elevated_until' => $elevatedUntil]);

        return $updated === 1 ? $elevatedUntil->toDateTimeImmutable() : null;
    }

    private function generateToken(): string
    {
        return rtrim(strtr(base64_encode(random_bytes(32)), '+/', '-_'), '=');
    }

    private function hash(string $token): string
    {
        return hash('sha256', $token);
    }
}
