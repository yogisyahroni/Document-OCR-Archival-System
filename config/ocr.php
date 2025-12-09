<?php

return [
    /*
    |--------------------------------------------------------------------------
    | OCR Engine Configuration
    |--------------------------------------------------------------------------
    |
    | These settings control which OCR engine is used for document processing
    | and how it behaves, including timeout values and language preferences.
    |
    */

    'engine' => [
        'default' => $_ENV['OCR_ENGINE'] ?? 'tesseract',
        'drivers' => [
            'tesseract' => [
                'executable' => $_ENV['TESSERACT_PATH'] ?? '/usr/bin/tesseract',
                'languages' => explode(',', $_ENV['TESSERACT_LANGUAGES'] ?? 'eng'),
                'config_options' => [
                    'tessedit_char_whitelist' => $_ENV['TESSERACT_CHAR_WHITELIST'] ?? null,
                    'user_defined_dpi' => (int) ($_ENV['TESSERACT_DPI'] ?? 300),
                ],
                'timeout' => (int) ($_ENV['TESSERACT_TIMEOUT'] ?? 300), // seconds
            ],
            'google_vision' => [
                'api_key' => $_ENV['GOOGLE_VISION_API_KEY'] ?? null,
                'api_endpoint' => $_ENV['GOOGLE_VISION_API_ENDPOINT'] ?? 'https://vision.googleapis.com/v1/images:annotate',
                'timeout' => (int) ($_ENV['GOOGLE_VISION_TIMEOUT'] ?? 60), // seconds
                'supported_features' => [
                    'DOCUMENT_TEXT_DETECTION',
                    'TEXT_DETECTION'
                ],
            ],
            'aws_textract' => [
                'region' => $_ENV['AWS_TEXTRACT_REGION'] ?? 'us-east-1',
                'version' => $_ENV['AWS_TEXTRACT_VERSION'] ?? 'latest',
                'timeout' => (int) ($_ENV['AWS_TEXTRACT_TIMEOUT'] ?? 120), // seconds
                'max_items' => (int) ($_ENV['AWS_TEXTRACT_MAX_ITEMS'] ?? 1000),
            ],
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Document Processing Configuration
    |--------------------------------------------------------------------------
    |
    | These settings control how documents are processed including file size
    | limits, supported formats, and preprocessing options.
    |
    */

    'document' => [
        'max_file_size' => (int) ($_ENV['OCR_MAX_FILE_SIZE'] ?? 10485760), // 10MB in bytes
        'supported_formats' => [
            'image' => explode(',', $_ENV['OCR_SUPPORTED_IMAGE_FORMATS'] ?? 'jpg,jpeg,png,tiff,tif,bmp,gif,webp'),
            'document' => explode(',', $_ENV['OCR_SUPPORTED_DOC_FORMATS'] ?? 'pdf'),
        ],
        'preprocessing' => [
            'resize_images' => [
                'enabled' => filter_var($_ENV['OCR_RESIZE_IMAGES'] ?? 'true', FILTER_VALIDATE_BOOLEAN),
                'max_width' => (int) ($_ENV['OCR_MAX_WIDTH'] ?? 2000),
                'max_height' => (int) ($_ENV['OCR_MAX_HEIGHT'] ?? 2000),
            ],
            'enhance_contrast' => filter_var($_ENV['OCR_ENHANCE_CONTRAST'] ?? 'false', FILTER_VALIDATE_BOOLEAN),
            'deskew' => filter_var($_ENV['OCR_DESKEW'] ?? 'false', FILTER_VALIDATE_BOOLEAN),
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Document Number Extraction Configuration
    |--------------------------------------------------------------------------
    |
    | These settings control how document numbers are extracted from OCR
    | results using regex patterns and validation rules.
    |
    */

    'extraction' => [
        'patterns' => [
            'invoice' => '/\b(?:INV|INVOICE)[-\s]?(\d{4,8})\b/i',
            'po' => '/\b(?:PO|PURCHASE ORDER)[-\s]?(\d{4,8})\b/i',
            'dn' => '/\b(?:DN|DELIVERY NOTE)[-\s]?(\d{4,8})\b/i',
            'general_number' => '/\b(?:NO|NOMOR|NUMBER)[-\s]?([A-Z0-9]{4,12})\b/i',
            'custom_pattern' => $_ENV['CUSTOM_DOC_NUMBER_PATTERN'] ?? null,
        ],
        'validation' => [
            'min_length' => (int) ($_ENV['EXTRACTION_MIN_LENGTH'] ?? 4),
            'max_length' => (int) ($_ENV['EXTRACTION_MAX_LENGTH'] ?? 50),
            'allow_alphanumeric' => filter_var($_ENV['EXTRACTION_ALLOW_ALPHANUMERIC'] ?? 'true', FILTER_VALIDATE_BOOLEAN),
            'require_prefix' => filter_var($_ENV['EXTRACTION_REQUIRE_PREFIX'] ?? 'false', FILTER_VALIDATE_BOOLEAN),
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Queue Configuration for OCR Jobs
    |--------------------------------------------------------------------------
    |
    | These settings control how OCR jobs are queued and processed including
    | retry mechanisms and worker configuration.
    |
    */

    'queue' => [
        'name' => $_ENV['OCR_QUEUE_NAME'] ?? 'ocr_processing',
        'connection' => $_ENV['OCR_QUEUE_CONNECTION'] ?? 'redis',
        'timeout' => (int) ($_ENV['OCR_QUEUE_TIMEOUT'] ?? 600), // 10 minutes for processing
        'max_attempts' => (int) ($_ENV['OCR_QUEUE_MAX_ATTEMPTS'] ?? 3),
        'retry_delay' => (int) ($_ENV['OCR_QUEUE_RETRY_DELAY'] ?? 60), // 1 minute
        'batch_size' => (int) ($_ENV['OCR_QUEUE_BATCH_SIZE'] ?? 10),
        'worker_processes' => (int) ($_ENV['OCR_WORKER_PROCESSES'] ?? 3),
        'dead_letter_queue' => $_ENV['OCR_DEAD_LETTER_QUEUE'] ?? 'ocr_failed',
    ],

    /*
    |--------------------------------------------------------------------------
    | Storage Configuration for OCR Results
    |--------------------------------------------------------------------------
    |
    | These settings control where OCR results are stored and how they are
    | accessed, including temporary file storage and result caching.
    |
    */

    'storage' => [
        'temp_dir' => $_ENV['OCR_TEMP_DIR'] ?? dirname(__DIR__) . '/storage/temp',
        'result_cache' => [
            'enabled' => filter_var($_ENV['OCR_RESULT_CACHE_ENABLED'] ?? 'true', FILTER_VALIDATE_BOOLEAN),
            'ttl' => (int) ($_ENV['OCR_RESULT_CACHE_TTL'] ?? 3600), // 1 hour
            'driver' => $_ENV['OCR_RESULT_CACHE_DRIVER'] ?? 'redis',
        ],
        'raw_output_storage' => [
            'enabled' => filter_var($_ENV['OCR_RAW_OUTPUT_STORAGE'] ?? 'false', FILTER_VALIDATE_BOOLEAN),
            'path' => $_ENV['OCR_RAW_OUTPUT_PATH'] ?? dirname(__DIR__) . '/storage/ocr_raw_outputs',
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Performance Configuration
    |--------------------------------------------------------------------------
    |
    | These settings control performance aspects of OCR processing including
    | parallel processing limits and memory usage.
    |
    */

    'performance' => [
        'max_parallel_processes' => (int) ($_ENV['OCR_MAX_PARALLEL_PROCESSES'] ?? 4),
        'memory_limit' => $_ENV['OCR_MEMORY_LIMIT'] ?? '1G',
        'page_segmentation_modes' => [
            'enabled' => true,
            'default' => 6, // Assume a single uniform block of text
            'modes' => [
                1 => 'Orientation and script detection (OSD) only',
                3 => 'Fully automatic page segmentation, but no OSD',
                4 => 'Assume a single column of text of variable sizes',
                5 => 'Assume a single uniform block of vertically aligned text',
                6 => 'Assume a single uniform block of text',
                7 => 'Treat the image as a single text line',
                8 => 'Treat the image as a single word',
                9 => 'Treat the image as a single word in a circle',
                10 => 'Treat the image as a single character',
            ],
        ],
        'ocr_confidence_threshold' => (float) ($_ENV['OCR_CONFIDENCE_THRESHOLD'] ?? 0.5),
    ],

    /*
    |--------------------------------------------------------------------------
    | Logging Configuration for OCR Operations
    |--------------------------------------------------------------------------
    |
    | These settings control the logging behavior for OCR operations including
    | log level, file destination, and sensitive data masking.
    |
    */

    'logging' => [
        'enabled' => filter_var($_ENV['OCR_LOGGING_ENABLED'] ?? 'true', FILTER_VALIDATE_BOOLEAN),
        'level' => $_ENV['OCR_LOG_LEVEL'] ?? 'info',
        'file' => $_ENV['OCR_LOG_FILE'] ?? dirname(__DIR__) . '/storage/logs/ocr.log',
        'mask_sensitive_data' => [
            'enabled' => filter_var($_ENV['OCR_MASK_SENSITIVE_DATA'] ?? 'true', FILTER_VALIDATE_BOOLEAN),
            'patterns' => [
                '/(password|token|key|secret|authorization)\s*[=:]\s*[^\s]+/i',
                '/Bearer\s+[a-zA-Z0-9\-\._~=+\/]+/',
            ],
        ],
    ],
];