<?php

declare(strict_types=1);

namespace App\Modules\Identity\Application\Http;

use App\Modules\Audit\Application\Contracts\SecurityAuditRecorder;
use App\Modules\Audit\Application\SecurityAuditEvent;
use App\Modules\Identity\Application\Contracts\ApiSessionManager;
use App\Modules\Identity\Application\Contracts\FederatedIdentityMapper;
use App\Modules\Identity\Application\Contracts\OidcAuthorizationService;
use App\Modules\Identity\Application\Contracts\OidcCallbackService;
use App\Modules\Identity\Application\Contracts\OidcIdentityVerifier;
use App\Modules\Identity\Application\Contracts\OidcTokenClient;
use App\Modules\Identity\Application\Contracts\TotpManager;
use App\Modules\Identity\Domain\TenantMembership;
use App\Modules\Identity\Domain\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use RuntimeException;
use Symfony\Component\HttpFoundation\Response;
use Throwable;

final readonly class AuthController
{
    public function __construct(
        private ApiSessionManager $sessions,
        private SecurityAuditRecorder $audit,
        private TotpManager $totp,
        private OidcAuthorizationService $federation,
        private OidcCallbackService $federationCallback,
        private OidcTokenClient $federationTokens,
        private OidcIdentityVerifier $federationVerifier,
        private FederatedIdentityMapper $federationMapper,
    ) {}

    public function login(Request $request): JsonResponse
    {
        $credentials = $request->validate([
            'email' => ['required', 'string', 'email', 'max:254'],
            'password' => ['required', 'string', 'max:1024'],
        ]);
        $user = User::query()->where('email', mb_strtolower($credentials['email']))->first();

        if (! $user instanceof User || ! Hash::check($credentials['password'], $user->getAuthPassword())) {
            $this->audit->record(new SecurityAuditEvent(
                'identity.login.failed',
                null,
                $this->requestFingerprint($request, $credentials['email']),
            ));

            return new JsonResponse([
                'error' => [
                    'code' => 'AUTH_INVALID_CREDENTIALS',
                    'message' => 'The provided credentials are invalid.',
                ],
            ], Response::HTTP_UNAUTHORIZED);
        }

        $issued = DB::transaction(function () use ($user, $request, $credentials) {
            $issued = $this->sessions->issue($user);
            $this->audit->record(new SecurityAuditEvent(
                'identity.login.succeeded',
                (string) $user->getKey(),
                [...$this->requestFingerprint($request, $credentials['email']), 'session_id' => $issued->id],
            ));

            return $issued;
        });

        return new JsonResponse([
            'data' => [
                'token_type' => 'Bearer',
                'access_token' => $issued->token,
                'session_id' => $issued->id,
                'expires_at' => $issued->expiresAt->format(DATE_ATOM),
            ],
        ], Response::HTTP_CREATED);
    }

    public function federationStart(): JsonResponse
    {
        try {
            $request = $this->federation->begin();
        } catch (RuntimeException) {
            return new JsonResponse(['error' => [
                'code' => 'AUTH_FEDERATION_UNAVAILABLE',
                'message' => 'Federated sign-in is not available.',
            ]], Response::HTTP_NOT_FOUND);
        }

        return new JsonResponse(['data' => [
            'authorization_url' => $request->authorizationUrl,
            'state' => $request->state,
            'expires_at' => $request->expiresAt,
        ]]);
    }

    public function federationCallback(Request $request): JsonResponse
    {
        try {
            $transaction = $this->federationCallback->consume(
                (string) $request->query('state', ''),
                is_string($request->query('code')) ? $request->query('code') : null,
                is_string($request->query('error')) ? $request->query('error') : null,
            );
            $identity = $this->federationVerifier->verify(
                $this->federationTokens->exchange($transaction['code'], $transaction['verifier'], $transaction['redirect_uri']),
                $transaction['nonce'],
            );
            $user = $this->federationMapper->resolve($identity);
            if ($user === null) {
                throw new RuntimeException('Federated subject is not linked.');
            }

            $issued = DB::transaction(function () use ($user, $request) {
                $issued = $this->sessions->issue($user);
                $this->audit->record(new SecurityAuditEvent(
                    'identity.federation.login.succeeded',
                    (string) $user->getAuthIdentifier(),
                    [...$this->requestFingerprint($request), 'session_id' => $issued->id],
                ));

                return $issued;
            });

            return new JsonResponse(['data' => [
                'token_type' => 'Bearer',
                'access_token' => $issued->token,
                'session_id' => $issued->id,
                'expires_at' => $issued->expiresAt->format(DATE_ATOM),
            ]], Response::HTTP_CREATED);
        } catch (Throwable) {
            return new JsonResponse(['error' => [
                'code' => 'AUTH_FEDERATION_UNAVAILABLE',
                'message' => 'Federated sign-in is not available.',
            ]], Response::HTTP_NOT_FOUND);
        }
    }

    public function logout(Request $request): JsonResponse
    {
        DB::transaction(function () use ($request): void {
            $sessionId = (string) $request->attributes->get('iam_session_id');
            $user = $request->user();
            $this->sessions->revoke($sessionId, $user);
            $this->audit->record(new SecurityAuditEvent(
                'identity.logout.succeeded',
                (string) $user->getAuthIdentifier(),
                [...$this->requestFingerprint($request), 'session_id' => $sessionId],
            ));
        });

        return new JsonResponse(null, Response::HTTP_NO_CONTENT);
    }

    public function tenants(Request $request): JsonResponse
    {
        $user = $request->user();
        abort_unless($user instanceof User, Response::HTTP_UNAUTHORIZED);

        $tenants = TenantMembership::query()
            ->join('tenants', 'tenants.id', '=', 'tenant_memberships.tenant_id')
            ->where('tenant_memberships.user_id', (string) $user->getKey())
            ->where('tenant_memberships.status', 'active')
            ->where('tenants.status', 'active')
            ->orderBy('tenants.name')
            ->get(['tenants.id', 'tenants.name', 'tenants.jurisdiction_code'])
            ->map(fn (object $tenant): array => [
                'id' => (string) $tenant->id,
                'name' => (string) $tenant->name,
                'jurisdictionCode' => (string) $tenant->jurisdiction_code,
            ])->values()->all();

        return new JsonResponse(['data' => $tenants]);
    }

    public function logoutAll(Request $request): JsonResponse
    {
        DB::transaction(function () use ($request): void {
            $user = $request->user();
            $revokedCount = $this->sessions->revokeAll($user);
            $this->audit->record(new SecurityAuditEvent(
                'identity.logout_all.succeeded',
                (string) $user->getAuthIdentifier(),
                [...$this->requestFingerprint($request), 'revoked_count' => $revokedCount],
            ));
        });

        return new JsonResponse(null, Response::HTTP_NO_CONTENT);
    }

    public function beginTotpEnrollment(Request $request): JsonResponse
    {
        $user = $request->user();
        if ($user->mfa_confirmed_at !== null) {
            return new JsonResponse(['error' => [
                'code' => 'MFA_ALREADY_ENABLED',
                'message' => 'TOTP MFA is already enabled.',
            ]], Response::HTTP_CONFLICT);
        }

        $issued = $this->totp->begin($user);

        return new JsonResponse(['data' => [
            'secret' => $issued->secret,
            'otpauth_uri' => $issued->otpauthUri,
        ]], Response::HTTP_CREATED);
    }

    public function confirmTotpEnrollment(Request $request): JsonResponse
    {
        $validated = $request->validate(['code' => ['required', 'string', 'regex:/^\d{6}$/']]);
        $user = $request->user();

        if (! $this->totp->confirm($user, $validated['code'])) {
            return new JsonResponse(['error' => [
                'code' => 'MFA_INVALID_CODE',
                'message' => 'The verification code is invalid.',
            ]], Response::HTTP_UNPROCESSABLE_ENTITY);
        }

        return new JsonResponse(['data' => ['factor' => 'totp', 'enabled' => true]]);
    }

    public function elevateMfa(Request $request): JsonResponse
    {
        $validated = $request->validate(['code' => ['required', 'string', 'regex:/^\d{6}$/']]);
        $user = $request->user();
        $sessionId = (string) $request->attributes->get('iam_session_id');

        if ($user->mfa_confirmed_at === null) {
            return new JsonResponse(['error' => [
                'code' => 'MFA_NOT_ENABLED',
                'message' => 'TOTP MFA is not enabled.',
            ]], Response::HTTP_CONFLICT);
        }

        try {
            $elevatedUntil = DB::transaction(function () use ($sessionId, $user, $request, $validated) {
                if (! $this->totp->verifyAndConsume($user, $validated['code'])) {
                    throw new RuntimeException('invalid_or_replayed');
                }

                $elevatedUntil = $this->sessions->elevate($sessionId, $user);
                if ($elevatedUntil === null) {
                    throw new RuntimeException('session_not_elevatable');
                }

                $this->audit->record(new SecurityAuditEvent(
                    'identity.mfa.elevated',
                    (string) $user->getAuthIdentifier(),
                    [...$this->requestFingerprint($request), 'session_id' => $sessionId, 'factor' => 'totp'],
                ));

                return $elevatedUntil;
            });
        } catch (RuntimeException $exception) {
            if ($exception->getMessage() === 'session_not_elevatable') {
                return new JsonResponse(['error' => [
                    'code' => 'AUTH_SESSION_NOT_ELEVATABLE',
                    'message' => 'The current session cannot be elevated.',
                ]], Response::HTTP_CONFLICT);
            }

            $this->audit->record(new SecurityAuditEvent(
                'identity.mfa.elevation_failed',
                (string) $user->getAuthIdentifier(),
                [...$this->requestFingerprint($request), 'session_id' => $sessionId],
            ));

            return new JsonResponse(['error' => [
                'code' => 'MFA_INVALID_OR_REPLAYED_CODE',
                'message' => 'The verification code is invalid or has already been used.',
            ]], Response::HTTP_UNPROCESSABLE_ENTITY);
        }

        return new JsonResponse(['data' => [
            'factor' => 'totp',
            'elevated_until' => $elevatedUntil->format(DATE_ATOM),
        ]]);
    }

    /** @return array<string, scalar|null> */
    private function requestFingerprint(Request $request, ?string $email = null): array
    {
        return array_filter([
            'principal_hash' => $email === null ? null : hash('sha256', mb_strtolower($email)),
            'ip_hash' => hash('sha256', (string) $request->ip()),
            'user_agent_hash' => hash('sha256', (string) $request->userAgent()),
        ], static fn (mixed $value): bool => $value !== null);
    }
}
