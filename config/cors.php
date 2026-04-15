<?php

$defaultOrigins = [
    env('FRONTEND_URL', 'http://ushia.net'),
    'http://ushia.net',
    'https://ushia.net',
    'http://www.ushia.net',
    'https://www.ushia.net',
    'http://localhost:3000',
    'http://127.0.0.1:3000',
    'http://localhost:3001',
    'http://127.0.0.1:3001',
];

$envOrigins = array_map(
    'trim',
    explode(',', (string) env('CORS_ALLOWED_ORIGINS', ''))
);

$allowedOrigins = array_values(array_unique(array_filter([
    ...$defaultOrigins,
    ...$envOrigins,
])));

return [

    /*
    |--------------------------------------------------------------------------
    | Cross-Origin Resource Sharing (CORS) Configuration
    |--------------------------------------------------------------------------
    |
    | Here you may configure your settings for cross-origin resource sharing
    | or "CORS". This determines what cross-origin operations may execute
    | in web browsers. You are free to adjust these settings as needed.
    |
    | To learn more: https://developer.mozilla.org/en-US/docs/Web/HTTP/CORS
    |
    */

    'paths' => ['api/*', 'sanctum/csrf-cookie'],

    'allowed_methods' => ['*'],

    'allowed_origins' => $allowedOrigins,

    'allowed_origins_patterns' => [],

    'allowed_headers' => ['*'],

    'exposed_headers' => [],

    'max_age' => 0,

    'supports_credentials' => true,

];
