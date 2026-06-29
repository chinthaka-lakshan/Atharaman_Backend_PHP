<?php

$defaultAllowedOrigins = [
    'http://localhost:5174',
    'http://localhost:5173',
    'https://atharaman.vercel.app',
    'https://atharaman-frontend.vercel.app',
];

$configuredOrigins = env('CORS_ALLOWED_ORIGINS');

$allowedOrigins = $configuredOrigins
    ? array_values(array_filter(array_map('trim', explode(',', $configuredOrigins))))
    : $defaultAllowedOrigins;

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

    'paths' => ['api/*', 'sanctum/csrf-cookie', 'login', 'register', 'logout', 'forgot-password', 'chatbot', '*'],

    'allowed_methods' => ['*'],

    'allowed_origins' => $allowedOrigins,

    'allowed_origins_patterns' => [],

    'allowed_headers' => ['*'],

    'exposed_headers' => [],

    'max_age' => 0,

    'supports_credentials' => true,

];
