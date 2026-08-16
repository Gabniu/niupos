<?php

declare(strict_types=1);

namespace App\Modules\Identity\Infrastructure;

use App\Modules\Identity\Application\Contracts\OidcDiscoveryClient;
use App\Modules\Identity\Application\Contracts\OidcTokenClient;
use App\Modules\Identity\Application\OidcTokenResponse;
use Illuminate\Support\Facades\Http;
use RuntimeException;

final readonly class HttpOidcTokenClient implements OidcTokenClient
{
    public function __construct(private OidcDiscoveryClient $discovery) {}

    public function exchange(string $code, string $verifier, string $redirectUri): OidcTokenResponse
    {
        if ($code === '' || $verifier === '' || ! str_starts_with($redirectUri, 'https://')) {
            throw new RuntimeException('The OIDC token request is invalid.');
        }

        $metadata = $this->discovery->metadata();
        $fields = [
            'grant_type' => 'authorization_code',
            'code' => $code,
            'redirect_uri' => $redirectUri,
            'client_id' => (string) config('identity.federation.client_id'),
            'code_verifier' => $verifier,
        ];
        $clientSecret = (string) config('identity.federation.client_secret', '');
        $request = Http::asForm()->acceptJson()->timeout(5);
        if ($clientSecret !== '') {
            $request = $request->withBasicAuth((string) config('identity.federation.client_id'), $clientSecret);
        }
        $response = $request->post($metadata->tokenEndpoint, $fields);
        if (! $response->successful()) {
            throw new RuntimeException('The OIDC token exchange failed.');
        }
        $body = $response->json();
        if (! is_array($body)
            || ! is_string($body['access_token'] ?? null)
            || ! is_string($body['token_type'] ?? null)
            || strcasecmp($body['token_type'], 'Bearer') !== 0
            || ! is_int($body['expires_in'] ?? null)
            || $body['expires_in'] < 1
            || ! is_string($body['id_token'] ?? null)) {
            throw new RuntimeException('The OIDC token response is incomplete.');
        }

        return new OidcTokenResponse(
            $body['access_token'],
            'Bearer',
            $body['expires_in'],
            $body['id_token'],
            is_string($body['refresh_token'] ?? null) ? $body['refresh_token'] : null,
            array_intersect_key($body, array_flip(['scope', 'session_state'])),
        );
    }
}
