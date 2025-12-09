<?php

use App\Config\Config;

return [
    /*
    |--------------------------------------------------------------------------
    | Application Name
    |--------------------------------------------------------------------------
    |
    | This value is the name of your application. This value is used when the
    | framework needs to place the application's name in a notification or
    | any other location as required by the application or its packages.
    |
    */

    'name' => $_ENV['APP_NAME'] ?? 'Document OCR & Archival System',

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

    'env' => $_ENV['APP_ENV'] ?? 'production',

    /*
    |--------------------------------------------------------------------------
    | Application Debug Mode
    |--------------------------------------------------------------------------
    |
    | When your application is in debug mode, detailed error messages with
    | stack traces will be shown on every error that occurs within your
    | application. If disabled, a simple generic error page is shown.
    |
    */

    'debug' => filter_var($_ENV['APP_DEBUG'] ?? false, FILTER_VALIDATE_BOOLEAN),

    /*
    |--------------------------------------------------------------------------
    | Application URL
    |--------------------------------------------------------------------------
    |
    | This URL is used by the console to properly generate URLs when using
    | the Artisan command line tool. You should set this to the root of
    | your application so that it is used when running Artisan tasks.
    |
    */

    'url' => $_ENV['APP_URL'] ?? 'http://localhost',
    'asset_url' => $_ENV['ASSET_URL'] ?? null,

    /*
    |--------------------------------------------------------------------------
    | Application Timezone
    |--------------------------------------------------------------------------
    |
    | Here you may specify the default timezone for your application, which
    | will be used by the PHP date and date-time functions. We have gone
    | ahead and set this to a sensible default for you out of the box.
    |
    */

    'timezone' => 'UTC',

    /*
    |--------------------------------------------------------------------------
    | Application Locale Configuration
    |--------------------------------------------------------------------------
    |
    | The application locale determines the default locale that will be used
    | by the translation service provider. You are free to set this value
    | to any of the locales which will be supported by the application.
    |
    */

    'locale' => 'en',

    /*
    |--------------------------------------------------------------------------
    | Application Fallback Locale
    |--------------------------------------------------------------------------
    |
    | The fallback locale determines the locale to use when the current one
    | is not available. You may change the value to correspond to any of
    | the language folders that are provided through your application.
    |
    */

    'fallback_locale' => 'en',

    /*
    |--------------------------------------------------------------------------
    | Faker Locale
    |--------------------------------------------------------------------------
    |
    | This locale will be used by the Faker PHP library when generating fake
    | data for your database seeds. For example, this will be used to get
    | localized telephone numbers, street address information and more.
    |
    */

    'faker_locale' => 'en_US',

    /*
    |--------------------------------------------------------------------------
    | Encryption Key
    |--------------------------------------------------------------------------
    |
    | This key is used by the Illuminate encrypter service and should be set
    | to a random, 32 character string, otherwise these encrypted strings
    | will not be safe. Please do this before deploying an application!
    |
    */

    'key' => $_ENV['APP_KEY'] ?? '',

    'cipher' => 'AES-256-CBC',

    /*
    |--------------------------------------------------------------------------
    | OCR Configuration
    |--------------------------------------------------------------------------
    |
    | These settings control the OCR processing behavior including the engine
    | to use, timeout values, and language preferences.
    |
    */

    'ocr' => [
        'engine' => $_ENV['OCR_ENGINE'] ?? 'tesseract',
        'timeout' => (int) ($_ENV['OCR_TIMEOUT'] ?? 300), // in seconds
        'language' => $_ENV['OCR_LANGUAGE'] ?? 'eng',
        'max_file_size' => (int) ($_ENV['OCR_MAX_FILE_SIZE'] ?? 10485760), // 10MB in bytes
        'supported_formats' => explode(',', $_ENV['OCR_SUPPORTED_FORMATS'] ?? 'pdf,jpg,jpeg,png,tiff'),
    ],

    /*
    |--------------------------------------------------------------------------
    | Queue Configuration
    |--------------------------------------------------------------------------
    |
    | These settings control the queue behavior for background processing
    | of OCR jobs and other asynchronous tasks.
    |
    */

    'queue' => [
        'default' => $_ENV['QUEUE_CONNECTION'] ?? 'redis',
        'failed' => [
            'driver' => $_ENV['QUEUE_FAILED_DRIVER'] ?? 'database',
            'database' => $_ENV['DB_CONNECTION'] ?? 'pgsql',
            'table' => 'failed_jobs',
        ],
        'ocr_queue' => [
            'name' => $_ENV['OCR_QUEUE_NAME'] ?? 'ocr_processing',
            'max_attempts' => (int) ($_ENV['OCR_MAX_ATTEMPTS'] ?? 3),
            'timeout' => (int) ($_ENV['OCR_JOB_TIMEOUT'] ?? 300), // 5 minutes
            'retry_delay' => (int) ($_ENV['OCR_RETRY_DELAY'] ?? 60), // 1 minute
            'batch_size' => (int) ($_ENV['OCR_BATCH_SIZE'] ?? 10),
            'worker_processes' => (int) ($_ENV['OCR_WORKER_PROCESSES'] ?? 3),
            'dead_letter_queue' => $_ENV['OCR_DEAD_LETTER_QUEUE'] ?? 'ocr_failed',
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | CORS Configuration
    |--------------------------------------------------------------------------
    |
    | Here you may configure your application's Cross-Origin Resource Sharing
    | settings. This determines what cross-origin operations may execute
    | in web browsers. You are free to adjust these settings as needed.
    |
    */

    'cors' => [
        'paths' => ['api/*', 'sanctum/csrf-cookie'],
        'allowed_methods' => ['*'],
        'allowed_origins' => explode(',', $_ENV['CORS_ALLOWED_ORIGINS'] ?? '*'),
        'allowed_origins_patterns' => [],
        'allowed_headers' => ['*'],
        'exposed_headers' => [],
        'max_age' => 0,
        'supports_credentials' => false,
    ],

    /*
    |--------------------------------------------------------------------------
    | Trusted Proxy Configuration
    |--------------------------------------------------------------------------
    |
    | When your application is behind a proxy that terminates SSL/TLS and
    | maps it to the next proxy or application, you should not trust
    | that proxy's IP addresses in any way. This directive allows you
    | to set a whitelist of trusted proxies for your application.
    |
    */

    'trustedproxy' => [
        'proxies' => $_ENV['TRUSTED_PROXIES'] ?? '*',
        'headers' => (
            \Symfony\Component\HttpFoundation\Request::HEADER_X_FORWARDED_FOR |
            \Symfony\Component\HttpFoundation\Request::HEADER_X_FORWARDED_HOST |
            \Symfony\Component\HttpFoundation\Request::HEADER_X_FORWARDED_PORT |
            \Symfony\Component\HttpFoundation\Request::HEADER_X_FORWARDED_PROTO |
            \Symfony\Component\HttpFoundation\Request::HEADER_X_FORWARDED_AWS_ELB
        ),
    ],

    /*
    |--------------------------------------------------------------------------
    | Maintenance Mode Driver
    |--------------------------------------------------------------------------
    |
    | These configuration options determine the driver used to determine and
    | manage Laravel's "maintenance mode" status. The "cache" driver will
    | allow maintenance mode to be controlled across multiple machines.
    |
    | Supported drivers: "file", "cache"
    |
    */

    'maintenance' => [
        'driver' => 'file',
        // 'store' => 'redis',
    ],
];