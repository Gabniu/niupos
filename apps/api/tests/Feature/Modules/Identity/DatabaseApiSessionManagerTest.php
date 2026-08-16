<?php

declare(strict_types=1);

namespace Tests\Feature\Modules\Identity;

use App\Modules\Identity\Application\Contracts\ApiSessionManager;
use App\Modules\Identity\Domain\ApiSession;
use App\Modules\Identity\Domain\User;
use DateInterval;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Date;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

final class DatabaseApiSessionManagerTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function it_stores_only_a_digest_and_authenticates_the_opaque_token(): void
    {
        $user = User::factory()->create();
        $manager = $this->app->make(ApiSessionManager::class);

        $issued = $manager->issue($user);
        $stored = ApiSession::query()->findOrFail($issued->id);

        self::assertSame(43, strlen($issued->token));
        self::assertNotSame($issued->token, $stored->getAttribute('token_hash'));
        self::assertSame(hash('sha256', $issued->token), $stored->getAttribute('token_hash'));
        self::assertTrue($user->is($manager->resolve($issued->token)?->user));
        self::assertNotNull($stored->fresh()?->getAttribute('last_used_at'));
    }

    #[Test]
    public function it_rejects_expired_and_unknown_tokens(): void
    {
        Date::setTestNow('2026-08-08 08:00:00');
        $user = User::factory()->create();
        $manager = $this->app->make(ApiSessionManager::class);
        $issued = $manager->issue($user, new DateInterval('PT1M'));

        Date::setTestNow('2026-08-08 08:02:00');

        self::assertNull($manager->resolve($issued->token));
        self::assertNull($manager->resolve('unknown-token'));
        Date::setTestNow();
    }

    #[Test]
    public function it_revokes_one_session_without_revoking_another(): void
    {
        $user = User::factory()->create();
        $manager = $this->app->make(ApiSessionManager::class);
        $first = $manager->issue($user);
        $second = $manager->issue($user);

        self::assertTrue($manager->revoke($first->id, $user));
        self::assertNull($manager->resolve($first->token));
        self::assertTrue($user->is($manager->resolve($second->token)?->user));
    }

    #[Test]
    public function it_revokes_all_sessions_owned_by_the_user_only(): void
    {
        $user = User::factory()->create();
        $otherUser = User::factory()->create();
        $manager = $this->app->make(ApiSessionManager::class);
        $first = $manager->issue($user);
        $second = $manager->issue($user);
        $other = $manager->issue($otherUser);

        self::assertSame(2, $manager->revokeAll($user));
        self::assertNull($manager->resolve($first->token));
        self::assertNull($manager->resolve($second->token));
        self::assertTrue($otherUser->is($manager->resolve($other->token)?->user));
    }
}
