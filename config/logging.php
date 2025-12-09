<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Default Log Channel
    |--------------------------------------------------------------------------
    |
    | This option defines the default log channel that gets used when writing
    | messages to the logs. The name specified in this option should match
    | one of the channels defined in the "channels" configuration array.
    |
    */

    'default' => $_ENV['LOG_CHANNEL'] ?? 'stack',

    /*
    |--------------------------------------------------------------------------
    | Deprecations Log Channel
    |--------------------------------------------------------------------------
    |
    | This option controls the log channel that should be used to log warnings
    | regarding deprecated PHP and library features. This allows you to get
    | your application ready for upcoming major versions of dependencies.
    |
    */

    'deprecations' => [
        'channel' => $_ENV['LOG_DEPRECATIONS_CHANNEL'] ?? 'null',
        'trace' => false,
    ],

    /*
    |--------------------------------------------------------------------------
    | Log Channels
    |--------------------------------------------------------------------------
    |
    | Here you may configure the log channels for your application. Laravel
    | uses the Monolog PHP logging library. This gives you a variety of
    | powerful log handlers / formatters to utilize.
    |
    | Available Drivers: "single", "daily", "slack", "syslog",
    |                    "errorlog", "monolog", "custom", "stack"
    |
    */

    'channels' => [
        'stack' => [
            'driver' => 'stack',
            'channels' => ['single'],
            'ignore_exceptions' => false,
        ],

        'single' => [
            'driver' => 'single',
            'path' => $_ENV['LOG_SINGLE_PATH'] ?? dirname(__DIR__) . '/storage/logs/laravel.log',
            'level' => $_ENV['LOG_LEVEL'] ?? 'info',
            'replace_placeholders' => true,
        ],

        'daily' => [
            'driver' => 'daily',
            'path' => $_ENV['LOG_DAILY_PATH'] ?? dirname(__DIR__) . '/storage/logs/laravel.log',
            'level' => $_ENV['LOG_LEVEL'] ?? 'info',
            'days' => (int) ($_ENV['LOG_DAILY_DAYS'] ?? 14),
            'replace_placeholders' => true,
        ],

        'slack' => [
            'driver' => 'slack',
            'url' => $_ENV['LOG_SLACK_WEBHOOK_URL'] ?? null,
            'username' => $_ENV['LOG_SLACK_USERNAME'] ?? 'Laravel Log',
            'emoji' => $_ENV['LOG_SLACK_EMOJI'] ?? ':boom:',
            'level' => $_ENV['LOG_LEVEL'] ?? 'critical',
            'replace_placeholders' => true,
        ],

        'papertrail' => [
            'driver' => 'monolog',
            'level' => $_ENV['LOG_LEVEL'] ?? 'debug',
            'handler' => \Monolog\Handler\SyslogUdpHandler::class,
            'handler_with' => [
                'host' => $_ENV['PAPERTRAIL_URL'] ?? null,
                'port' => $_ENV['PAPERTRAIL_PORT'] ?? null,
            ],
            'processors' => [\Monolog\Processor\PsrLogMessageProcessor::class],
        ],

        'stderr' => [
            'driver' => 'monolog',
            'level' => $_ENV['LOG_LEVEL'] ?? 'debug',
            'handler' => \Monolog\Handler\StreamHandler::class,
            'formatter' => $_ENV['LOG_STDERR_FORMATTER'] ?? null,
            'with' => [
                'stream' => 'php://stderr',
            ],
            'processors' => [\Monolog\Processor\PsrLogMessageProcessor::class],
        ],

        'syslog' => [
            'driver' => 'syslog',
            'level' => $_ENV['LOG_LEVEL'] ?? 'debug',
            'facility' => LOG_USER,
            'replace_placeholders' => true,
        ],

        'errorlog' => [
            'driver' => 'errorlog',
            'level' => $_ENV['LOG_LEVEL'] ?? 'debug',
            'replace_placeholders' => true,
        ],

        'null' => [
            'driver' => 'monolog',
            'handler' => \Monolog\Handler\NullHandler::class,
        ],

        'emergency' => [
            'path' => $_ENV['LOG_EMERGENCY_PATH'] ?? dirname(__DIR__) . '/storage/logs/laravel.log',
        ],

        /*
        |--------------------------------------------------------------------------
        | Application-Specific Logging Channels
        |--------------------------------------------------------------------------
        |
        | These channels are specifically configured for the Document OCR & Archival
        | System to handle different types of logs with appropriate formatting
        | and destinations.
        |
        */

        'ocr_processing' => [
            'driver' => 'daily',
            'path' => $_ENV['OCR_LOG_PATH'] ?? dirname(__DIR__) . '/storage/logs/ocr-processing.log',
            'level' => $_ENV['OCR_LOG_LEVEL'] ?? 'info',
            'days' => (int) ($_ENV['OCR_LOG_RETENTION_DAYS'] ?? 30),
            'replace_placeholders' => true,
            'formatter' => \Monolog\Formatter\JsonFormatter::class,
        ],

        'document_search' => [
            'driver' => 'daily',
            'path' => $_ENV['SEARCH_LOG_PATH'] ?? dirname(__DIR__) . '/storage/logs/document-search.log',
            'level' => $_ENV['SEARCH_LOG_LEVEL'] ?? 'info',
            'days' => (int) ($_ENV['SEARCH_LOG_RETENTION_DAYS'] ?? 30),
            'replace_placeholders' => true,
        ],

        'api_access' => [
            'driver' => 'daily',
            'path' => $_ENV['API_LOG_PATH'] ?? dirname(__DIR__) . '/storage/logs/api-access.log',
            'level' => $_ENV['API_LOG_LEVEL'] ?? 'info',
            'days' => (int) ($_ENV['API_LOG_RETENTION_DAYS'] ?? 30),
            'replace_placeholders' => true,
        ],

        'security' => [
            'driver' => 'daily',
            'path' => $_ENV['SECURITY_LOG_PATH'] ?? dirname(__DIR__) . '/storage/logs/security.log',
            'level' => 'warning',
            'days' => (int) ($_ENV['SECURITY_LOG_RETENTION_DAYS'] ?? 90),
            'replace_placeholders' => true,
        ],

        /*
        |--------------------------------------------------------------------------
        | Loki Logging Configuration
        |--------------------------------------------------------------------------
        |
        | Configuration for sending logs to Grafana Loki for centralized logging
        | and monitoring.
        |
        */

        'loki' => [
            'driver' => 'custom',
            'via' => \App\Logging\LokiLoggerFactory::class,
            'level' => $_ENV['LOKI_LOG_LEVEL'] ?? 'info',
            'formatter' => \Monolog\Formatter\JsonFormatter::class,
            'loki_url' => $_ENV['LOKI_URL'] ?? 'http://localhost:3100',
            'labels' => [
                'application' => $_ENV['APP_NAME'] ?? 'document-ocr-system',
                'environment' => $_ENV['APP_ENV'] ?? 'development',
            ],
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Log Processing Configuration
    |--------------------------------------------------------------------------
    |
    | These settings control how logs are processed, including log levels for
    | different components and log retention policies.
    |
    */

    'processing' => [
        'log_levels' => [
            'document_service' => $_ENV['DOCUMENT_SERVICE_LOG_LEVEL'] ?? 'info',
            'ocr_service' => $_ENV['OCR_SERVICE_LOG_LEVEL'] ?? 'info',
            'search_service' => $_ENV['SEARCH_SERVICE_LOG_LEVEL'] ?? 'info',
            'auth_service' => $_ENV['AUTH_SERVICE_LOG_LEVEL'] ?? 'info',
        ],
        'retention_days' => [
            'default' => (int) ($_ENV['DEFAULT_LOG_RETENTION_DAYS'] ?? 7),
            'error_logs' => (int) ($_ENV['ERROR_LOG_RETENTION_DAYS'] ?? 30),
            'security_logs' => (int) ($_ENV['SECURITY_LOG_RETENTION_DAYS'] ?? 90),
        ],
        'sensitive_data_masking' => [
            'enabled' => filter_var($_ENV['LOG_SENSITIVE_DATA_MASKING'] ?? true, FILTER_VALIDATE_BOOLEAN),
            'patterns' => [
                '(password|token|key|secret|authorization)\\s*[=:]\\s*[\'"]?[^\'"\\s]+[\'"]?',
                'Bearer\\s+[a-zA-Z0-9\\-._~=+/]+',
                'api[_\\-]?(key|token)\\s*[=:]\\s*[\'"]?[^\'"\\s]+[\'"]?',
            ],
        ],
    ],
];