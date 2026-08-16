<?php

declare(strict_types=1);

namespace Tests\Feature\Modules\Identity;

use App\Modules\Audit\Domain\AuditEvent;
use App\Modules\Identity\Domain\ApiSession;
use App\Modules\Identity\Domain\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Date;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

final class MfaHttpTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function an_authenticated_user_can_enroll_and_elevate_the_current_session(): void
    {
        Date::setTestNow('2026-08-08 12:00:00');
        User::factory()->create(['email' => 'owner@nova.test', 'password' => 'correct-password']);
        $token = $this->login();

        $enrollment = $this->withToken($token)->postJson('/api/v1/auth/mfa/totp/enrollment')
            ->assertCreated();
        $secret = (string) $enrollment->json('data.secret');
        $code = $this->code($secret, Date::now()->getTimestamp());

        $this->withToken($token)->postJson('/api/v1/auth/mfa/totp/confirmation', ['code' => $code])
            ->assertOk()->assertJsonPath('data.enabled', true);
        $this->withToken($token)->postJson('/api/v1/auth/mfa/elevation', ['code' => $code])
            ->assertOk()->assertJsonPath('data.elevated_until', '2026-08-08T12:05:00+00:00');

        $session = ApiSession::query()->firstOrFail();
        self::assertSame('2026-08-08T12:05:00+00:00', $session->mfa_elevated_until->toAtomString());
        self::assertNotNull(AuditEvent::query()->where('event_type', 'identity.mfa.elevated')->first());
        Date::setTestNow();
    }

    #[Test]
    public function a_totp_time_step_can_only_elevate_once_across_sessions(): void
    {
        Date::setTestNow('2026-08-08 12:00:00');
        User::factory()->create(['email' => 'owner@nova.test', 'password' => 'correct-password']);
        $first = $this->login();
        $second = $this->login();
        $secret = (string) $this->withToken($first)->postJson('/api/v1/auth/mfa/totp/enrollment')
            ->assertCreated()->json('data.secret');
        $code = $this->code($secret, Date::now()->getTimestamp());
        $this->withToken($first)->postJson('/api/v1/auth/mfa/totp/confirmation', ['code' => $code])->assertOk();

        $this->withToken($first)->postJson('/api/v1/auth/mfa/elevation', ['code' => $code])->assertOk();
        $this->withToken($second)->postJson('/api/v1/auth/mfa/elevation', ['code' => $code])
            ->assertUnprocessable()->assertJsonPath('error.code', 'MFA_INVALID_OR_REPLAYED_CODE');
        self::assertSame(1, AuditEvent::query()->where('event_type', 'identity.mfa.elevation_failed')->count());
        Date::setTestNow();
    }

    #[Test]
    public function enrollment_requires_authentication_and_does_not_replace_an_enabled_factor(): void
    {
        $this->postJson('/api/v1/auth/mfa/totp/enrollment')->assertUnauthorized();
        User::factory()->create(['email' => 'owner@nova.test', 'password' => 'correct-password']);
        $token = $this->login();
        $secret = (string) $this->withToken($token)->postJson('/api/v1/auth/mfa/totp/enrollment')
            ->assertCreated()->json('data.secret');
        $this->withToken($token)->postJson('/api/v1/auth/mfa/totp/confirmation', [
            'code' => $this->code($secret, now()->getTimestamp()),
        ])->assertOk();

        $this->withToken($token)->postJson('/api/v1/auth/mfa/totp/enrollment')
            ->assertConflict()->assertJsonPath('error.code', 'MFA_ALREADY_ENABLED');
    }

    #[Test]
    public function mfa_verification_attempts_are_throttled_per_session_and_ip(): void
    {
        User::factory()->create([
            'email' => 'owner@nova.test',
            'password' => 'correct-password',
            'mfa_secret' => str_repeat('A', 32),
            'mfa_confirmed_at' => now(),
        ]);
        $token = $this->login();

        for ($attempt = 0; $attempt < 5; $attempt++) {
            $this->withToken($token)->postJson('/api/v1/auth/mfa/elevation', ['code' => '000000'])
                ->assertUnprocessable();
        }

        $this->withToken($token)->postJson('/api/v1/auth/mfa/elevation', ['code' => '000000'])
            ->assertTooManyRequests();
    }

    private function login(): string
    {
        return (string) $this->postJson('/api/v1/auth/login', [
            'email' => 'owner@nova.test',
            'password' => 'correct-password',
        ])->assertCreated()->json('data.access_token');
    }

    private function code(string $secret, int $timestamp): string
    {
        $alphabet = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ234567';
        $bits = '';
        foreach (str_split($secret) as $character) {
            $bits .= str_pad(decbin((int) strpos($alphabet, $character)), 5, '0', STR_PAD_LEFT);
        }
        $key = '';
        foreach (str_split($bits, 8) as $chunk) {
            if (strlen($chunk) === 8) {
                $key .= chr(bindec($chunk));
            }
        }
        $counter = intdiv($timestamp, 30);
        $binary = pack('N2', intdiv($counter, 4294967296), $counter % 4294967296);
        $hash = hash_hmac('sha1', $binary, $key, true);
        $offset = ord($hash[19]) & 15;
        $value = ((ord($hash[$offset]) & 127) << 24)
            | ((ord($hash[$offset + 1]) & 255) << 16)
            | ((ord($hash[$offset + 2]) & 255) << 8)
            | (ord($hash[$offset + 3]) & 255);

        return str_pad((string) ($value % 1000000), 6, '0', STR_PAD_LEFT);
    }
}
