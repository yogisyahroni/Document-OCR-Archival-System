<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Default Queue Connection Name
    |--------------------------------------------------------------------------
    |
    | Laravel's queue API supports an assortment of backends via a single
    | API, giving you convenient access to each backend using the same
    | syntax for every one. Here you may define a default connection.
    |
    */

    'default' => $_ENV['QUEUE_CONNECTION'] ?? 'redis',

    /*
    |--------------------------------------------------------------------------
    | Queue Connections
    |--------------------------------------------------------------------------
    |
    | Here you may configure the connection information for each server that
    | is used by your application. An example configuration is provided
    | for each backend supported by Laravel. You're also free to add more.
    |
    | Drivers: "sync", "database", "beanstalkd", "sqs", "redis", "null"
    |
    */

    'connections' => [
        'sync' => [
            'driver' => 'sync',
        ],

        'database' => [
            'driver' => 'database',
            'table' => 'jobs',
            'queue' => 'default',
            'retry_after' => 90,
            'after_commit' => false,
        ],

        'beanstalkd' => [
            'driver' => 'beanstalkd',
            'host' => $_ENV['BEANSTALKD_HOST'] ?? 'localhost',
            'queue' => 'default',
            'retry_after' => 90,
            'block_for' => 0,
            'after_commit' => false,
        ],

        'sqs' => [
            'driver' => 'sqs',
            'key' => $_ENV['AWS_ACCESS_KEY_ID'] ?? '',
            'secret' => $_ENV['AWS_SECRET_ACCESS_KEY'] ?? '',
            'prefix' => $_ENV['SQS_PREFIX'] ?? 'https://sqs.us-east-1.amazonaws.com/your-account-id',
            'queue' => $_ENV['SQS_QUEUE'] ?? 'default',
            'suffix' => $_ENV['SQS_SUFFIX'] ?? '',
            'region' => $_ENV['AWS_DEFAULT_REGION'] ?? 'us-east-1',
            'after_commit' => false,
        ],

        'redis' => [
            'driver' => 'redis',
            'connection' => $_ENV['QUEUE_REDIS_CONNECTION'] ?? 'default',
            'queue' => $_ENV['QUEUE_REDIS_QUEUE'] ?? 'default',
            'retry_after' => (int) ($_ENV['QUEUE_RETRY_AFTER'] ?? 90),
            'block_for' => null,
            'after_commit' => true,
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Failed Queue Jobs
    |--------------------------------------------------------------------------
    |
    | These options configure the behavior of failed queue job logging so you
    | can control which database and table are used to store the jobs that
    | have failed. You may change them to any database / table you wish.
    |
    */

    'failed' => [
        'driver' => $_ENV['QUEUE_FAILED_DRIVER'] ?? 'database',
        'database' => $_ENV['DB_CONNECTION'] ?? 'pgsql',
        'table' => 'failed_jobs',
    ],

    /*
    |--------------------------------------------------------------------------
    | OCR Queue Configuration
    |--------------------------------------------------------------------------
    |
    | These settings specifically configure the OCR queue behavior including
    | the number of workers, timeout values, and retry mechanisms.
    |
    */

    'ocr_queue' => [
        'name' => $_ENV['OCR_QUEUE_NAME'] ?? 'ocr_processing',
        'max_attempts' => (int) ($_ENV['OCR_MAX_ATTEMPTS'] ?? 3),
        'timeout' => (int) ($_ENV['OCR_JOB_TIMEOUT'] ?? 300), // 5 minutes
        'retry_delay' => (int) ($_ENV['OCR_RETRY_DELAY'] ?? 60), // 1 minute
        'batch_size' => (int) ($_ENV['OCR_BATCH_SIZE'] ?? 10),
        'worker_processes' => (int) ($_ENV['OCR_WORKER_PROCESSES'] ?? 3),
        'dead_letter_queue' => $_ENV['OCR_DEAD_LETTER_QUEUE'] ?? 'ocr_failed',
    ],

    /*
    |--------------------------------------------------------------------------
    | Queue Monitoring Configuration
    |--------------------------------------------------------------------------
    |
    | These settings control the monitoring of queue performance including
    | metrics collection and alert thresholds.
    |
    */

    'monitoring' => [
        'enabled' => filter_var($_ENV['QUEUE_MONITORING_ENABLED'] ?? 'true', FILTER_VALIDATE_BOOLEAN),
        'metrics_collection' => [
            'enabled' => filter_var($_ENV['QUEUE_METRICS_ENABLED'] ?? 'true', FILTER_VALIDATE_BOOLEAN),
            'collection_interval' => (int) ($_ENV['QUEUE_METRICS_INTERVAL'] ?? 60), // seconds
        ],
        'alert_thresholds' => [
            'failed_jobs' => (int) ($_ENV['QUEUE_FAILED_JOBS_THRESHOLD'] ?? 10),
            'queue_length' => (int) ($_ENV['QUEUE_LENGTH_THRESHOLD'] ?? 1000),
            'processing_time' => (int) ($_ENV['QUEUE_PROCESSING_TIME_THRESHOLD'] ?? 300), // seconds
        ],
    ],
];