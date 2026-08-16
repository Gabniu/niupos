<?php

declare(strict_types=1);

namespace App\Modules\Identity\Infrastructure;

use App\Modules\Identity\Application\Contracts\OidcDiscoveryClient;
use App\Modules\Identity\Application\Contracts\OidcIdentityVerifier;
use App\Modules\Identity\Application\FederatedIdentity;
use App\Modules\Identity\Application\OidcTokenResponse;
use DateTimeImmutable;
use DateTimeZone;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Lcobucci\JWT\Configuration;
use Lcobucci\JWT\Signer\Eddsa;
use Lcobucci\JWT\Signer\Key\InMemory;
use Lcobucci\JWT\Validation\Constraint\IssuedBy;
use Lcobucci\JWT\Validation\Constraint\PermittedFor;
use Lcobucci\JWT\Validation\Constraint\SignedWith;
use RuntimeException;

final readonly class LcobucciOidcIdentityVerifier implements OidcIdentityVerifier
{
    public function __construct(private OidcDiscoveryClient $discovery) {}

    public function verify(OidcTokenResponse $tokens, string $expectedNonce): FederatedIdentity
    {
        if ($expectedNonce === '' || $tokens->idToken === '') {
            throw new RuntimeException('The OIDC identity response is incomplete.');
        }
        $metadata = $this->discovery->metadata();
        $parser = Configuration::forAsymmetricSigner(new Eddsa(), InMemory::plainText('unused'), InMemory::plainText('unused'))->parser();
        try {
            $token = $parser->parse($tokens->idToken);
        } catch (\Throwable) {
            throw new RuntimeException('The OIDC identity token is malformed.');
        }
        $header = $token->headers()->all();
        if (($header['alg'] ?? null) !== 'EdDSA' || ! is_string($header['kid'] ?? null) || $header['kid'] === '') {
            throw new RuntimeException('The OIDC identity token algorithm or key is invalid.');
        }
        $jwk = $this->key($metadata->jwksUri, $header['kid']);
        $key = InMemory::plainText($jwk);
        $config = Configuration::forAsymmetricSigner(new Eddsa(), $key, $key);
        try {
            $config->validator()->assert($token, new SignedWith(new Eddsa(), $key), new IssuedBy($metadata->issuer), new PermittedFor((string) config('identity.federation.client_id')));
        } catch (\Throwable) {
            throw new RuntimeException('The OIDC identity token failed cryptographic validation.');
        }

        $claims = $token->claims()->all();
        $now = time();
        $skew = max(0, (int) config('identity.federation.clock_skew_seconds', 60));
        $expiresAt = $claims['exp'] ?? null;
        $issuedAt = $claims['iat'] ?? null;
        $subject = $claims['sub'] ?? null;
        if (! $expiresAt instanceof DateTimeImmutable || ! $issuedAt instanceof DateTimeImmutable || ! is_string($subject) || $subject === ''
            || $expiresAt->getTimestamp() < $now - $skew || $issuedAt->getTimestamp() > $now + $skew
            || ! hash_equals($expectedNonce, (string) ($claims['nonce'] ?? ''))) {
            throw new RuntimeException('The OIDC identity token claims are invalid.');
        }

        return new FederatedIdentity(
            $metadata->issuer,
            $subject,
            is_string($claims['email'] ?? null) ? $claims['email'] : null,
            is_string($claims['name'] ?? null) ? $claims['name'] : null,
            $expiresAt,
            $claims,
        );
    }

    private function key(string $jwksUri, string $kid): string
    {
        $keys = Cache::remember('nova.identity.jwks.'.hash('sha256', $jwksUri), 3600, function () use ($jwksUri): array {
            $response = Http::acceptJson()->timeout(5)->get($jwksUri);
            if (! $response->successful() || ! is_array($response->json('keys'))) {
                throw new RuntimeException('The OIDC signing keys could not be loaded.');
            }

            return $response->json('keys');
        });
        foreach ($keys as $jwk) {
            if (is_array($jwk) && ($jwk['kid'] ?? null) === $kid && ($jwk['kty'] ?? null) === 'OKP' && ($jwk['crv'] ?? null) === 'Ed25519' && ($jwk['alg'] ?? null) === 'EdDSA' && is_string($jwk['x'] ?? null)) {
                $decoded = base64_decode(strtr($jwk['x'], '-_', '+/').'===', true);
                if (is_string($decoded) && strlen($decoded) === SODIUM_CRYPTO_SIGN_PUBLICKEYBYTES) {
                    return $decoded;
                }
            }
        }

        throw new RuntimeException('The OIDC signing key was not found.');
    }
}
