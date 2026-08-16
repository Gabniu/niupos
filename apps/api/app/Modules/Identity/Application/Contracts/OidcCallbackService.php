<?php

declare(strict_types=1);

namespace App\Modules\Identity\Application\Contracts;

interface OidcCallbackService
{
    /** @return array{nonce:string,verifier:string,redirect_uri:string,code:string} */
    public function consume(string $state, ?string $code, ?string $error): array;
}
