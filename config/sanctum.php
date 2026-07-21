<?php

use Illuminate\Cookie\Middleware\EncryptCookies;
use Illuminate\Foundation\Http\Middleware\ValidateCsrfToken;
use Laravel\Sanctum\Http\Middleware\AuthenticateSession;

return [
    // API v1 is bearer-token only. The existing Inertia application keeps its
    // Fortify session authentication on the web middleware stack.
    'stateful' => [],
    'guard' => [],

    // Thirty days. Individual tokens also receive an explicit expires_at.
    'expiration' => (int) env('SANCTUM_EXPIRATION', 43200),
    'token_prefix' => env('SANCTUM_TOKEN_PREFIX', 'ls_'),

    'middleware' => [
        'authenticate_session' => AuthenticateSession::class,
        'encrypt_cookies' => EncryptCookies::class,
        'validate_csrf_token' => ValidateCsrfToken::class,
    ],
];
