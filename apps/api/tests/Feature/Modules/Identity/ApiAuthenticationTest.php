<?php

declare(strict_types=1);

namespace Tests\Feature\Modules\Identity;

use App\Modules\Identity\Domain\ApiSession;
use App\Modules\Identity\Domain\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

final class ApiAuthenticationTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function a_user_can_login_and_logout_with_an_opaque_bearer_token(): void
    {
        $user = User::factory()->create(['email' => 'owner@nova.test', 'password' => 'correct-password']);

        $login = $this->postJson('/api/v1/auth/login', [
            'email' => 'OWNER@NOVA.TEST',
            'password' => 'correct-password',
        ])->assertCreated()->assertJsonPath('data.token_type', 'Bearer');
        $token = $login->json('data.access_token');
        $sessionId = $login->json('data.session_id');

        self::assertNotSame($token, ApiSession::query()->findOrFail($sessionId)->token_hash);

        $this->withToken($token)->postJson('/api/v1/auth/logout')->assertNoContent();
        $this->withToken($token)->postJson('/api/v1/auth/logout')->assertUnauthorized()
            ->assertJsonPath('error.code', 'AUTH_UNAUTHENTICATED');
        self::assertNotNull(ApiSession::query()->findOrFail($sessionId)->revoked_at);
        self::assertSame($user->getKey(), ApiSession::query()->findOrFail($sessionId)->user_id);
    }

    #[Test]
    public function invalid_credentials_return_the_same_public_error(): void
    {
        User::factory()->create(['email' => 'known@nova.test', 'password' => 'correct-password']);

        foreach ([
            ['email' => 'known@nova.test', 'password' => 'wrong-password'],
            ['email' => 'unknown@nova.test', 'password' => 'wrong-password'],
        ] as $credentials) {
            $this->postJson('/api/v1/auth/login', $credentials)
                ->assertUnauthorized()
                ->assertExactJson([
                    'error' => [
                        'code' => 'AUTH_INVALID_CREDENTIALS',
                        'message' => 'The provided credentials are invalid.',
                    ],
                ]);
        }
    }

    #[Test]
    public function login_attempts_are_throttled_by_normalized_email_and_ip(): void
    {
        for ($attempt = 1; $attempt <= 5; $attempt++) {
            $this->postJson('/api/v1/auth/login', [
                'email' => 'throttle@nova.test',
                'password' => 'wrong-password',
            ])->assertUnauthorized();
        }

        $this->postJson('/api/v1/auth/login', [
            'email' => 'THROTTLE@NOVA.TEST',
            'password' => 'wrong-password',
        ])->assertTooManyRequests();
    }

    #[Test]
    public function logout_all_revokes_every_session_for_the_authenticated_user(): void
    {
        User::factory()->create(['email' => 'manager@nova.test', 'password' => 'correct-password']);
        $first = $this->login('manager@nova.test');
        $second = $this->login('manager@nova.test');

        $this->withToken($first)->postJson('/api/v1/auth/logout-all')->assertNoContent();
        $this->withToken($first)->postJson('/api/v1/auth/logout')->assertUnauthorized();
        $this->withToken($second)->postJson('/api/v1/auth/logout')->assertUnauthorized();
    }

    private function login(string $email): string
    {
        return (string) $this->postJson('/api/v1/auth/login', [
            'email' => $email,
            'password' => 'correct-password',
        ])->assertCreated()->json('data.access_token');
    }
}
