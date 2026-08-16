<?php

declare(strict_types=1);

namespace Tests\Feature\Modules\Audit;

use App\Modules\Audit\Domain\AuditEvent;
use App\Modules\Identity\Domain\User;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

final class SecurityAuditEvidenceTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function login_outcomes_record_secret_safe_evidence(): void
    {
        $user = User::factory()->create([
            'email' => 'audited@nova.test',
            'password' => 'correct-password',
        ]);

        $this->withHeader('User-Agent', 'NOVA Test Client')->postJson('/api/v1/auth/login', [
            'email' => 'audited@nova.test',
            'password' => 'wrong-password',
        ])->assertUnauthorized();
        $response = $this->withHeader('User-Agent', 'NOVA Test Client')->postJson('/api/v1/auth/login', [
            'email' => 'audited@nova.test',
            'password' => 'correct-password',
        ])->assertCreated();

        $failed = AuditEvent::query()->where('event_type', 'identity.login.failed')->firstOrFail();
        $succeeded = AuditEvent::query()->where('event_type', 'identity.login.succeeded')->firstOrFail();
        $encodedEvidence = json_encode([$failed->metadata, $succeeded->metadata], JSON_THROW_ON_ERROR);

        self::assertNull($failed->actor_user_id);
        self::assertSame($user->getKey(), $succeeded->actor_user_id);
        self::assertSame($response->json('data.session_id'), $succeeded->metadata['session_id']);
        self::assertStringNotContainsString('audited@nova.test', $encodedEvidence);
        self::assertStringNotContainsString('correct-password', $encodedEvidence);
        self::assertStringNotContainsString($response->json('data.access_token'), $encodedEvidence);
    }

    #[Test]
    public function audit_events_are_append_only(): void
    {
        User::factory()->create(['email' => 'immutable@nova.test', 'password' => 'correct-password']);
        $this->postJson('/api/v1/auth/login', [
            'email' => 'immutable@nova.test',
            'password' => 'correct-password',
        ])->assertCreated();
        $event = AuditEvent::query()->firstOrFail();

        $this->expectException(QueryException::class);
        $event->update(['event_type' => 'tampered']);
    }
}
