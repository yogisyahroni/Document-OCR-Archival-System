<?php

return [
    /*
    |--------------------------------------------------------------------------
    | System Configuration
    |--------------------------------------------------------------------------
    |
    | These settings control the overall system behavior including document
    | processing limits, API rate limits, and general system parameters.
    |
    */

    'document_processing' => [
        'max_concurrent_uploads' => (int) ($_ENV['MAX_CONCURRENT_UPLOADS'] ?? 10),
        'max_documents_per_user' => (int) ($_ENV['MAX_DOCUMENTS_PER_USER'] ?? 10000),
        'max_search_results' => (int) ($_ENV['MAX_SEARCH_RESULTS'] ?? 1000),
        'processing_timeout' => (int) ($_ENV['DOCUMENT_PROCESSING_TIMEOUT'] ?? 600), // 10 minutes
        'file_validation' => [
            'strict_mode' => filter_var($_ENV['FILE_VALIDATION_STRICT'] ?? 'true', FILTER_VALIDATE_BOOLEAN),
            'allowed_mime_types' => explode(',', $_ENV['ALLOWED_MIME_TYPES'] ?? 'application/pdf,image/jpeg,image/png,image/tiff'),
            'max_filename_length' => (int) ($_ENV['MAX_FILENAME_LENGTH'] ?? 255),
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | API Rate Limiting Configuration
    |--------------------------------------------------------------------------
    |
    | These settings control the rate limiting behavior for API endpoints
    | to prevent abuse and ensure fair usage.
    |
    */

    'rate_limiting' => [
        'enabled' => filter_var($_ENV['RATE_LIMITING_ENABLED'] ?? 'true', FILTER_VALIDATE_BOOLEAN),
        'default_limits' => [
            'uploads' => [
                'requests' => (int) ($_ENV['UPLOAD_RATE_LIMIT_REQUESTS'] ?? 10),
                'period' => (int) ($_ENV['UPLOAD_RATE_LIMIT_PERIOD'] ?? 3600), // 1 hour
            ],
            'searches' => [
                'requests' => (int) ($_ENV['SEARCH_RATE_LIMIT_REQUESTS'] ?? 100),
                'period' => (int) ($_ENV['SEARCH_RATE_LIMIT_PERIOD'] ?? 3600), // 1 hour
            ],
            'downloads' => [
                'requests' => (int) ($_ENV['DOWNLOAD_RATE_LIMIT_REQUESTS'] ?? 50),
                'period' => (int) ($_ENV['DOWNLOAD_RATE_LIMIT_PERIOD'] ?? 3600), // 1 hour
            ],
        ],
        'user_specific_limits' => [
            'premium' => [
                'uploads' => [
                    'requests' => (int) ($_ENV['PREMIUM_UPLOAD_RATE_LIMIT_REQUESTS'] ?? 50),
                    'period' => (int) ($_ENV['PREMIUM_UPLOAD_RATE_LIMIT_PERIOD'] ?? 3600),
                ],
                'searches' => [
                    'requests' => (int) ($_ENV['PREMIUM_SEARCH_RATE_LIMIT_REQUESTS'] ?? 500),
                    'period' => (int) ($_ENV['PREMIUM_SEARCH_RATE_LIMIT_PERIOD'] ?? 3600),
                ],
            ],
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Security Configuration
    |--------------------------------------------------------------------------
    |
    | These settings control various security aspects of the system including
    | authentication, authorization, and data protection measures.
    |
    */

    'security' => [
        'jwt' => [
            'secret' => $_ENV['JWT_SECRET'] ?? '',
            'refresh_secret' => $_ENV['JWT_REFRESH_SECRET'] ?? '',
            'ttl' => (int) ($_ENV['JWT_TTL'] ?? 15), // minutes
            'refresh_ttl' => (int) ($_ENV['JWT_REFRESH_TTL'] ?? 10080), // minutes (7 days)
        ],
        'password_policy' => [
            'min_length' => (int) ($_ENV['PASSWORD_MIN_LENGTH'] ?? 8),
            'require_uppercase' => filter_var($_ENV['PASSWORD_REQUIRE_UPPERCASE'] ?? 'true', FILTER_VALIDATE_BOOLEAN),
            'require_lowercase' => filter_var($_ENV['PASSWORD_REQUIRE_LOWERCASE'] ?? 'true', FILTER_VALIDATE_BOOLEAN),
            'require_numbers' => filter_var($_ENV['PASSWORD_REQUIRE_NUMBERS'] ?? 'true', FILTER_VALIDATE_BOOLEAN),
            'require_special_chars' => filter_var($_ENV['PASSWORD_REQUIRE_SPECIAL_CHARS'] ?? 'false', FILTER_VALIDATE_BOOLEAN),
        ],
        'file_security' => [
            'virus_scan' => [
                'enabled' => filter_var($_ENV['VIRUS_SCAN_ENABLED'] ?? 'false', FILTER_VALIDATE_BOOLEAN),
                'engine' => $_ENV['VIRUS_SCAN_ENGINE'] ?? 'clamav',
                'timeout' => (int) ($_ENV['VIRUS_SCAN_TIMEOUT'] ?? 30),
            ],
            'content_validation' => [
                'enabled' => filter_var($_ENV['CONTENT_VALIDATION_ENABLED'] ?? 'true', FILTER_VALIDATE_BOOLEAN),
                'allowed_content_types' => explode(',', $_ENV['ALLOWED_CONTENT_TYPES'] ?? 'text/plain,text/html,application/xml'),
            ],
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Monitoring & Metrics Configuration
    |--------------------------------------------------------------------------
    |
    | These settings control the monitoring and metrics collection for the system
    | including performance metrics, usage statistics, and alert thresholds.
    |
    */

    'monitoring' => [
        'prometheus' => [
            'enabled' => filter_var($_ENV['PROMETHEUS_ENABLED'] ?? 'true', FILTER_VALIDATE_BOOLEAN),
            'metrics_prefix' => $_ENV['PROMETHEUS_METRICS_PREFIX'] ?? 'document_ocr_',
            'storage_adapter' => $_ENV['PROMETHEUS_STORAGE_ADAPTER'] ?? 'apc',
            'apc_prefix' => $_ENV['PROMETHEUS_APC_PREFIX'] ?? 'prom_',
        ],
        'metrics' => [
            'collect_performance_metrics' => filter_var($_ENV['COLLECT_PERFORMANCE_METRICS'] ?? 'true', FILTER_VALIDATE_BOOLEAN),
            'collect_usage_metrics' => filter_var($_ENV['COLLECT_USAGE_METRICS'] ?? 'true', FILTER_VALIDATE_BOOLEAN),
            'collection_interval' => (int) ($_ENV['METRICS_COLLECTION_INTERVAL'] ?? 60), // seconds
        ],
        'alerts' => [
            'enabled' => filter_var($_ENV['ALERTS_ENABLED'] ?? 'true', FILTER_VALIDATE_BOOLEAN),
            'thresholds' => [
                'high_error_rate' => (float) ($_ENV['HIGH_ERROR_RATE_THRESHOLD'] ?? 0.1), // 10%
                'long_processing_time' => (int) ($_ENV['LONG_PROCESSING_TIME_THRESHOLD'] ?? 300), // seconds
                'high_queue_length' => (int) ($_ENV['HIGH_QUEUE_LENGTH_THRESHOLD'] ?? 1000),
                'low_disk_space' => (float) ($_ENV['LOW_DISK_SPACE_THRESHOLD'] ?? 0.1), // 10% remaining
            ],
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Notification Configuration
    |--------------------------------------------------------------------------
    |
    | These settings control how notifications are sent to users and administrators
    | including email settings, webhook endpoints, and SMS providers.
    |
    */

    'notifications' => [
        'email' => [
            'enabled' => filter_var($_ENV['EMAIL_NOTIFICATIONS_ENABLED'] ?? 'true', FILTER_VALIDATE_BOOLEAN),
            'from_address' => $_ENV['MAIL_FROM_ADDRESS'] ?? 'noreply@document-ocr-system.com',
            'from_name' => $_ENV['MAIL_FROM_NAME'] ?? 'Document OCR System',
        ],
        'webhook' => [
            'enabled' => filter_var($_ENV['WEBHOOK_NOTIFICATIONS_ENABLED'] ?? 'false', FILTER_VALIDATE_BOOLEAN),
            'endpoints' => [
                'document_processed' => $_ENV['DOCUMENT_PROCESSED_WEBHOOK'] ?? null,
                'system_alert' => $_ENV['SYSTEM_ALERT_WEBHOOK'] ?? null,
            ],
            'timeout' => (int) ($_ENV['WEBHOOK_TIMEOUT'] ?? 30),
            'retries' => (int) ($_ENV['WEBHOOK_RETRIES'] ?? 3),
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Backup & Retention Configuration
    |--------------------------------------------------------------------------
    |
    | These settings control the backup schedules and data retention policies
    | for documents and system metadata.
    |
    */

    'backup' => [
        'enabled' => filter_var($_ENV['BACKUP_ENABLED'] ?? 'false', FILTER_VALIDATE_BOOLEAN),
        'schedule' => $_ENV['BACKUP_SCHEDULE'] ?? '0 2 * * *', // Daily at 2 AM
        'retention' => [
            'documents' => (int) ($_ENV['DOCUMENT_RETENTION_DAYS'] ?? 365), // 1 year
            'logs' => (int) ($_ENV['LOG_RETENTION_DAYS'] ?? 30), // 30 days
            'metadata' => (int) ($_ENV['METADATA_RETENTION_DAYS'] ?? 730), // 2 years
        ],
        'destination' => [
            'local' => $_ENV['BACKUP_LOCAL_PATH'] ?? dirname(__DIR__) . '/storage/backups',
            'remote' => [
                'enabled' => filter_var($_ENV['REMOTE_BACKUP_ENABLED'] ?? 'false', FILTER_VALIDATE_BOOLEAN),
                'provider' => $_ENV['REMOTE_BACKUP_PROVIDER'] ?? 's3',
                'bucket' => $_ENV['REMOTE_BACKUP_BUCKET'] ?? null,
            ],
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Audit Trail Configuration
    |--------------------------------------------------------------------------
    |
    | These settings control the auditing of user actions and system events
    | for compliance and security purposes.
    |
    */

    'audit_trail' => [
        'enabled' => filter_var($_ENV['AUDIT_TRAIL_ENABLED'] ?? 'true', FILTER_VALIDATE_BOOLEAN),
        'tracked_events' => [
            'document_upload',
            'document_download',
            'document_delete',
            'document_search',
            'user_login',
            'user_logout',
            'admin_actions',
        ],
        'retention_days' => (int) ($_ENV['AUDIT_RETENTION_DAYS'] ?? 90),
        'exclude_events' => explode(',', $_ENV['AUDIT_EXCLUDE_EVENTS'] ?? 'document_view,health_check'),
    ],
];