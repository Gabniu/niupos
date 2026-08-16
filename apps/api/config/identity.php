<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Shared NOVA Identity federation
    |--------------------------------------------------------------------------
    |
    | These values prepare the consumer-side OIDC boundary. Federation stays
    | disabled until the issuer, client registration, token verifier, subject
    | mapping, and rollback acceptance suite are all enabled together.
    */
    'federation' => [
        'enabled' => (bool) env('NOVA_IDENTITY_FEDERATION_ENABLED', false),
        'issuer' => rtrim((string) env('NOVA_IDENTITY_ISSUER', 'https://novaauth.niuautomations.com/api/auth'), '/'),
        'client_id' => (string) env('NOVA_IDENTITY_CLIENT_ID', ''),
        'client_secret' => (string) env('NOVA_IDENTITY_CLIENT_SECRET', ''),
        'redirect_uri' => (string) env('NOVA_IDENTITY_REDIRECT_URI', ''),
        'audience' => (string) env('NOVA_IDENTITY_AUDIENCE', ''),
        'clock_skew_seconds' => (int) env('NOVA_IDENTITY_CLOCK_SKEW_SECONDS', 60),
        'discovery_cache_seconds' => (int) env('NOVA_IDENTITY_DISCOVERY_CACHE_SECONDS', 3600),
    ],
];
