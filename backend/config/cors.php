<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Cross-Origin Resource Sharing (CORS) Configuration
    |--------------------------------------------------------------------------
    |
    | CORS determines which cross-origin browser requests are allowed to
    | access the application's API.
    |
    */

    'paths' => [
        'api/*',
        'sanctum/csrf-cookie',
    ],

    /*
    |--------------------------------------------------------------------------
    | Allowed Methods
    |--------------------------------------------------------------------------
    |
    | Only methods currently used by the TKB API are allowed cross-origin.
    |
    */

    'allowed_methods' => [
        'GET',
        'POST',
        'PUT',
        'PATCH',
        'DELETE',
        'OPTIONS',
    ],

    /*
    |--------------------------------------------------------------------------
    | Allowed Origins
    |--------------------------------------------------------------------------
    |
    | Configure frontend origins through the TKB_FRONTEND_URLS environment
    | variable.
    |
    | Example:
    |
    | TKB_FRONTEND_URLS=http://localhost:3000,http://127.0.0.1:3000
    |
    | Production example:
    |
    | TKB_FRONTEND_URLS=https://www.example.com,https://admin.example.com
    |
    */

    'allowed_origins' => array_values(
        array_filter(
            array_map(
                'trim',
                explode(
                    ',',
                    env(
                        'TKB_FRONTEND_URLS',
                        'http://localhost:3000,http://127.0.0.1:3000'
                    )
                )
            )
        )
    ),

    /*
    |--------------------------------------------------------------------------
    | Allowed Origin Patterns
    |--------------------------------------------------------------------------
    */

    'allowed_origins_patterns' => [],

    /*
    |--------------------------------------------------------------------------
    | Allowed Headers
    |--------------------------------------------------------------------------
    |
    | Authorization is required for Sanctum Bearer tokens.
    | Content-Type and Accept are required for normal JSON API requests.
    | X-Requested-With is included for compatibility with common clients.
    |
    */

    'allowed_headers' => [
        'Accept',
        'Authorization',
        'Content-Type',
        'Origin',
        'X-Requested-With',
    ],

    /*
    |--------------------------------------------------------------------------
    | Exposed Headers
    |--------------------------------------------------------------------------
    */

    'exposed_headers' => [],

    /*
    |--------------------------------------------------------------------------
    | Preflight Cache
    |--------------------------------------------------------------------------
    |
    | Cache successful OPTIONS responses for one hour.
    |
    */

    'max_age' => 3600,

    /*
    |--------------------------------------------------------------------------
    | Credentials
    |--------------------------------------------------------------------------
    |
    | TKB currently uses Sanctum Bearer token authentication rather than
    | cookie-based SPA authentication.
    |
    */

    'supports_credentials' => true,

];