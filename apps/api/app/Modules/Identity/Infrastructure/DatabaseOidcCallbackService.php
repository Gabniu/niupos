<?php

declare(strict_types=1);

namespace App\Modules\Identity\Infrastructure;

use App\Modules\Identity\Application\Contracts\OidcCallbackService;
use Illuminate\Support\Facades\Cache;
use RuntimeException;

final class DatabaseOidcCallbackService implements OidcCallbackService
{
    public function consume(string $state, ?string $code, ?string $error): array
    {
        if (! (bool) config('identity.federation.enabled', false) || ! preg_match('/^[A-Za-z0-9_-]{32,128}$/', $state)) {
            throw new RuntimeException('Federated callback is unavailable.');
        }

        $transaction = Cache::pull('nova.identity.oidc.state.'.$state);
        if (! is_array($transaction)
            || ! is_string($transaction['nonce'] ?? null)
            || ! is_string($transaction['verifier'] ?? null)
            || ! is_string($transaction['redirect_uri'] ?? null)) {
            throw new RuntimeException('Federated callback state is invalid or expired.');
        }
        if ($error !== null || ! is_string($code) || $code === '') {
            throw new RuntimeException('Federated authorization was not completed.');
        }

        return [
            'nonce' => $transaction['nonce'],
            'verifier' => $transaction['verifier'],
            'redirect_uri' => $transaction['redirect_uri'],
            'code' => $code,
        ];
    }
}
