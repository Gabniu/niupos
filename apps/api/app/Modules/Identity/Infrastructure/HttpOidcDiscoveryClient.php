<?php

declare(strict_types=1);

namespace App\Modules\Identity\Infrastructure;

use App\Modules\Identity\Application\Contracts\OidcDiscoveryClient;
use App\Modules\Identity\Application\OidcProviderMetadata;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use RuntimeException;

final class HttpOidcDiscoveryClient implements OidcDiscoveryClient
{
    public function metadata(): OidcProviderMetadata
    {
        $issuer = (string) config('identity.federation.issuer');
        if ($issuer === '' || ! str_starts_with($issuer, 'https://')) {
            throw new RuntimeException('NOVA Identity federation issuer must use HTTPS.');
        }

        $parsedIssuer = parse_url($issuer);
        $host = is_array($parsedIssuer) ? ($parsedIssuer['scheme'] ?? '').'://'.($parsedIssuer['host'] ?? '') : '';
        $discoveryUrl = $host.'/.well-known/openid-configuration'.($parsedIssuer['path'] ?? '');
        $cacheKey = 'nova.identity.oidc.'.hash('sha256', $issuer);
        $document = Cache::remember($cacheKey, (int) config('identity.federation.discovery_cache_seconds', 3600), function () use ($discoveryUrl): array {
            $response = Http::acceptJson()->timeout(5)->get($discoveryUrl);
            if (! $response->successful()) {
                throw new RuntimeException('NOVA Identity discovery could not be loaded.');
            }

            return $response->json();
        });

        $required = ['issuer', 'authorization_endpoint', 'token_endpoint', 'jwks_uri', 'userinfo_endpoint'];
        foreach ($required as $key) {
            if (! is_string($document[$key] ?? null) || ! str_starts_with($document[$key], 'https://')) {
                throw new RuntimeException('NOVA Identity discovery is incomplete or not HTTPS.');
            }
        }
        if ((string) $document['issuer'] !== $issuer || ! in_array('S256', $document['code_challenge_methods_supported'] ?? [], true)) {
            throw new RuntimeException('NOVA Identity discovery failed issuer or PKCE validation.');
        }

        return new OidcProviderMetadata(
            $issuer,
            $document['authorization_endpoint'],
            $document['token_endpoint'],
            $document['jwks_uri'],
            $document['userinfo_endpoint'],
            array_values(array_filter($document['code_challenge_methods_supported'] ?? [], 'is_string')),
        );
    }
}
