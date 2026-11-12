<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Application Name
    |--------------------------------------------------------------------------
    |
    | This value is the name of your application, which will be used when the
    | framework needs to place the application's name in a notification or
    | other UI elements where an application name needs to be displayed.
    |
    */

    'name' => env('APP_NAME', 'Laravel'),

    /*
    |--------------------------------------------------------------------------
    | Application Environment
    |--------------------------------------------------------------------------
    |
    | This value determines the "environment" your application is currently
    | running in. This may determine how you prefer to configure various
    | services the application utilizes. Set this in your ".env" file.
    |
    */

    'env' => env('APP_ENV', 'production'),

    /*
    |--------------------------------------------------------------------------
    | Application Debug Mode
    |--------------------------------------------------------------------------
    |
    | When application debug mode is enabled, detailed error messages with
    | stack traces will be shown on every error that occurs within your
    | application.
    |
    */

    'debug' => (bool) env('APP_DEBUG', false),

    /*
    |--------------------------------------------------------------------------
    | Application URL
    |--------------------------------------------------------------------------
    |
    | This URL is used by the console to properly generate URLs when using
    | the Artisan command line tool.
    |
    */

    'url' => env('APP_URL', 'http://localhost'),

    /*
    |--------------------------------------------------------------------------
    | Application Timezone
    |--------------------------------------------------------------------------
    |
    | The default timezone used by the application.
    |
    */

    'timezone' => 'UTC',

    /*
    |--------------------------------------------------------------------------
    | Application Locale Configuration
    |--------------------------------------------------------------------------
    |
    | The default application locale and fallback locale.
    |
    | Supported locales for TKB:
    |
    | en -> English / LTR
    | fa -> فارسی / RTL
    |
    */

    'locale' => env('APP_LOCALE', 'en'),

    'fallback_locale' => env(
        'APP_FALLBACK_LOCALE',
        'en'
    ),

    'faker_locale' => env(
        'APP_FAKER_LOCALE',
        'en_US'
    ),

    /*
    |--------------------------------------------------------------------------
    | Supported Locales
    |--------------------------------------------------------------------------
    |
    | These are the locales officially supported by TKB.
    |
    */

    'supported_locales' => [
        'en',
        'fa',
    ],

    /*
    |--------------------------------------------------------------------------
    | Encryption Key
    |--------------------------------------------------------------------------
    |
    | This key is utilized by Laravel's encryption services and should be set
    | to a random, 32 character string to ensure all encrypted values are
    | secure.
    |
    */

    'cipher' => 'AES-256-CBC',

    'key' => env('APP_KEY'),

    'previous_keys' => [
        ...array_filter(
            explode(
                ',',
                (string) env(
                    'APP_PREVIOUS_KEYS',
                    ''
                )
            )
        ),
    ],

    /*
    |--------------------------------------------------------------------------
    | Maintenance Mode Driver
    |--------------------------------------------------------------------------
    |
    | These options determine the driver used to determine and manage the
    | application's maintenance mode.
    |
    */

    'maintenance' => [
        'driver' => env(
            'APP_MAINTENANCE_DRIVER',
            'file'
        ),

        'store' => env(
            'APP_MAINTENANCE_STORE',
            'database'
        ),
    ],

];
