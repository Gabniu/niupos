<?php

declare(strict_types=1);

namespace App\Modules\Identity\Infrastructure;

use App\Modules\Identity\Application\Contracts\OidcAuthorizationService;
use App\Modules\Identity\Application\Contracts\OidcDiscoveryClient;
use App\Modules\Identity\Application\OidcAuthorizationRequest;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;
use RuntimeException;

final readonly class DatabaseOidcAuthorizationService implements OidcAuthorizationService
{
    public function __construct(private OidcDiscoveryClient $discovery) {}

    public function begin(): OidcAuthorizationRequest
    {
        if (! (bool) config('identity.federation.enabled', false)) {
            throw new RuntimeException('NOVA Identity federation is disabled.');
        }

        $clientId = (string) config('identity.federation.client_id');
        $redirectUri = (string) config('identity.federation.redirect_uri');
        if ($clientId === '' || $redirectUri === '' || ! str_starts_with($redirectUri, 'https://')) {
            throw new RuntimeException('NOVA Identity federation client configuration is incomplete.');
        }

        $metadata = $this->discovery->metadata();
        if (! in_array('S256', $metadata->codeChallengeMethods, true)) {
            throw new RuntimeException('NOVA Identity provider does not support S256 PKCE.');
        }

        $state = self::randomToken();
        $nonce = self::randomToken();
        $verifier = self::randomToken(48);
        $challenge = self::base64Url(hash('sha256', $verifier, true));
        $expiresAt = now()->addMinutes(10);
        Cache::put('nova.identity.oidc.state.'.$state, [
            'nonce' => $nonce,
            'verifier' => $verifier,
            'redirect_uri' => $redirectUri,
        ], $expiresAt);

        $query = http_build_query([
            'response_type' => 'code',
            'client_id' => $clientId,
            'redirect_uri' => $redirectUri,
            'scope' => implode(' ', ['openid', 'profile', 'email']),
            'state' => $state,
            'nonce' => $nonce,
            'code_challenge' => $challenge,
            'code_challenge_method' => 'S256',
        ], '', '&', PHP_QUERY_RFC3986);

        return new OidcAuthorizationRequest($metadata->authorizationEndpoint.'?'.$query, $state, $expiresAt->toIso8601String());
    }

    private static function randomToken(int $bytes = 32): string
    {
        return self::base64Url(random_bytes($bytes));
    }

    private static function base64Url(string $value): string
    {
        return rtrim(strtr(base64_encode($value), '+/', '-_'), '=');
    }
}
