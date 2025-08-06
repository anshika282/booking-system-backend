<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Cross-Origin Resource Sharing (CORS) Configuration
    |--------------------------------------------------------------------------
    |
    | Here you may configure your settings for cross-origin resource sharing
    | or "CORS". This determines which origins are allowed to access your
    | application's API endpoints. You can find the full list of
    | options in the Laravel documentation.
    |
    */

    'paths' => [
        'api/*', // Allow CORS for all of our v1 API routes
    ],

    'allowed_methods' => ['*'],

    'allowed_origins' => [
        // Add the URL of your Vue development server here
        'http://localhost:5173',
        'http://127.0.0.1:5173',
    ],

    'allowed_origins_patterns' => [],

    'allowed_headers' => ['*'],

    'exposed_headers' => [],

    'max_age' => 0,

    'supports_credentials' => false,

];