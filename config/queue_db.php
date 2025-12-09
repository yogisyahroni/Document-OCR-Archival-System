<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Queue Database Tables Configuration
    |--------------------------------------------------------------------------
    |
    | These settings define the database tables used for queue management
    | including job tracking, batch management, and failed job logging.
    |
    */

    'tables' => [
        'jobs' => [
            'table_name' => $_ENV['QUEUE_JOBS_TABLE'] ?? 'jobs',
            'columns' => [
                'id' => 'id',
                'queue' => 'queue',
                'payload' => 'payload',
                'attempts' => 'attempts',
                'reserved_at' => 'reserved_at',
                'available_at' => 'available_at',
                'created_at' => 'created_at',
            ]
        ],
        'failed_jobs' => [
            'table_name' => $_ENV['QUEUE_FAILED_JOBS_TABLE'] ?? 'failed_jobs',
            'columns' => [
                'id' => 'id',
                'uuid' => 'uuid',
                'connection' => 'connection',
                'queue' => 'queue',
                'payload' => 'payload',
                'exception' => 'exception',
                'failed_at' => 'failed_at',
            ]
        ],
        'job_batches' => [
            'table_name' => $_ENV['QUEUE_JOB_BATCHES_TABLE'] ?? 'job_batches',
            'columns' => [
                'id' => 'id',
                'name' => 'name',
                'total_jobs' => 'total_jobs',
                'pending_jobs' => 'pending_jobs',
                'failed_jobs' => 'failed_jobs',
                'failed_job_ids' => 'failed_job_ids',
                'options' => 'options',
                'cancelled_at' => 'cancelled_at',
                'created_at' => 'created_at',
                'finished_at' => 'finished_at',
            ]
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Queue Database Connection
    |--------------------------------------------------------------------------
    |
    | Specify the database connection to use for queue tables. This can be
    | different from the default database connection if needed.
    |
    */

    'connection' => $_ENV['QUEUE_DB_CONNECTION'] ?? 'pgsql',

    /*
    |--------------------------------------------------------------------------
    | Queue Workers Configuration
    |--------------------------------------------------------------------------
    |
    | These settings control the behavior of queue workers including timeout
    | values, retry mechanisms, and concurrency settings.
    |
    */

    'workers' => [
        'sleep' => (int) ($_ENV['QUEUE_WORKER_SLEEP'] ?? 3),
        'max_job_time' => (int) ($_ENV['QUEUE_WORKER_MAX_JOB_TIME'] ?? 60), // seconds
        'max_attempts' => (int) ($_ENV['QUEUE_WORKER_MAX_ATTEMPTS'] ?? 3),
        'backoff' => (int) ($_ENV['QUEUE_WORKER_BACKOFF'] ?? 1), // seconds
        'force_https' => filter_var($_ENV['QUEUE_FORCE_HTTPS'] ?? false, FILTER_VALIDATE_BOOLEAN),
        'stop_when_empty' => filter_var($_ENV['QUEUE_STOP_WHEN_EMPTY'] ?? false, FILTER_VALIDATE_BOOLEAN),
        'name' => $_ENV['QUEUE_WORKER_NAME'] ?? gethostname(),
    ],

    /*
    |--------------------------------------------------------------------------
    | OCR Queue Specific Settings
    |--------------------------------------------------------------------------
    |
    | These settings specifically control the OCR processing queue behavior
    | including longer timeouts and retry mechanisms for heavy processing jobs.
    |
    */

    'ocr_queue' => [
        'name' => $_ENV['OCR_QUEUE_NAME'] ?? 'ocr_processing',
        'connection' => $_ENV['OCR_QUEUE_CONNECTION'] ?? 'redis',
        'timeout' => (int) ($_ENV['OCR_QUEUE_TIMEOUT'] ?? 600), // 10 minutes for OCR jobs
        'max_attempts' => (int) ($_ENV['OCR_QUEUE_MAX_ATTEMPTS'] ?? 5),
        'retry_delay' => (int) ($_ENV['OCR_QUEUE_RETRY_DELAY'] ?? 120), // 2 minutes
        'concurrent_jobs' => (int) ($_ENV['OCR_QUEUE_CONCURRENT_JOBS'] ?? 1),
        'dead_letter_queue' => $_ENV['OCR_QUEUE_DEAD_LETTER_NAME'] ?? 'ocr_failed',
        'batch_size' => (int) ($_ENV['OCR_QUEUE_BATCH_SIZE'] ?? 1),
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
        'enabled' => filter_var($_ENV['QUEUE_MONITORING_ENABLED'] ?? true, FILTER_VALIDATE_BOOLEAN),
        'metrics' => [
            'collect_job_metrics' => filter_var($_ENV['QUEUE_COLLECT_JOB_METRICS'] ?? true, FILTER_VALIDATE_BOOLEAN),
            'collect_worker_metrics' => filter_var($_ENV['QUEUE_COLLECT_WORKER_METRICS'] ?? true, FILTER_VALIDATE_BOOLEAN),
            'collection_interval' => (int) ($_ENV['QUEUE_METRICS_COLLECTION_INTERVAL'] ?? 60), // seconds
        ],
        'alerts' => [
            'failed_jobs_threshold' => (int) ($_ENV['QUEUE_FAILED_JOBS_ALERT_THRESHOLD'] ?? 10),
            'queue_length_threshold' => (int) ($_ENV['QUEUE_LENGTH_ALERT_THRESHOLD'] ?? 1000),
            'processing_time_threshold' => (int) ($_ENV['QUEUE_PROCESSING_TIME_ALERT_THRESHOLD'] ?? 300), // seconds
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Queue Security Configuration
    |--------------------------------------------------------------------------
    |
    | These settings control security aspects of the queue system including
    | payload encryption and access controls.
    |
    */

    'security' => [
        'encrypt_payloads' => filter_var($_ENV['QUEUE_ENCRYPT_PAYLOADS'] ?? false, FILTER_VALIDATE_BOOLEAN),
        'encryption_key' => $_ENV['QUEUE_ENCRYPTION_KEY'] ?? '',
        'signed_jobs' => filter_var($_ENV['QUEUE_SIGNED_JOBS'] ?? true, FILTER_VALIDATE_BOOLEAN),
    ],
];