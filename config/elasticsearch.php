<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Elasticsearch Configuration
    |--------------------------------------------------------------------------
    |
    | These settings control the connection to the Elasticsearch/OpenSearch
    | instance for full-text search and document indexing.
    |
    */

    'hosts' => [
        $_ENV['ELASTICSEARCH_HOST'] ?? 'localhost:9200'
    ],

    'scheme' => $_ENV['ELASTICSEARCH_SCHEME'] ?? 'http',

    'user' => $_ENV['ELASTICSEARCH_USER'] ?? null,

    'pass' => $_ENV['ELASTICSEARCH_PASS'] ?? null,

    /*
    |--------------------------------------------------------------------------
    | SSL Configuration
    |--------------------------------------------------------------------------
    |
    | These settings control SSL/TLS connection to Elasticsearch if using
    | HTTPS scheme or requiring authentication.
    |
    */

    'ssl_verification' => filter_var($_ENV['ELASTICSEARCH_SSL_VERIFICATION'] ?? 'true', FILTER_VALIDATE_BOOLEAN),

    'ca_cert' => $_ENV['ELASTICSEARCH_CA_CERT'] ?? null,

    'cert' => $_ENV['ELASTICSEARCH_CERT'] ?? null,

    'key' => $_ENV['ELASTICSEARCH_KEY'] ?? null,

    /*
    |--------------------------------------------------------------------------
    | Index Configuration
    |--------------------------------------------------------------------------
    |
    | These settings define the default index names and mapping configurations
    | for different types of searchable content.
    |
    */

    'indexes' => [
        'documents' => [
            'index' => $_ENV['DOCUMENTS_INDEX_NAME'] ?? 'documents',
            'settings' => [
                'number_of_shards' => (int) ($_ENV['DOCUMENTS_SHARDS'] ?? 1),
                'number_of_replicas' => (int) ($_ENV['DOCUMENTS_REPLICAS'] ?? 0),
                'refresh_interval' => $_ENV['DOCUMENTS_REFRESH_INTERVAL'] ?? '1s',
                'index.max_result_window' => (int) ($_ENV['DOCUMENTS_MAX_RESULT_WINDOW'] ?? 10000),
            ],
            'mappings' => [
                'properties' => [
                    'id' => [
                        'type' => 'keyword'
                    ],
                    'title' => [
                        'type' => 'text',
                        'analyzer' => 'standard',
                        'fields' => [
                            'keyword' => [
                                'type' => 'keyword',
                                'ignore_above' => 256
                            ]
                        ]
                    ],
                    'description' => [
                        'type' => 'text',
                        'analyzer' => 'standard'
                    ],
                    'extracted_doc_number' => [
                        'type' => 'text',
                        'analyzer' => 'keyword',
                        'fields' => [
                            'keyword' => [
                                'type' => 'keyword',
                                'ignore_above' => 256
                            ]
                        ]
                    ],
                    'full_text' => [
                        'type' => 'text',
                        'analyzer' => 'standard'
                    ],
                    'category_id' => [
                        'type' => 'integer'
                    ],
                    'uploaded_by_id' => [
                        'type' => 'integer'
                    ],
                    'status' => [
                        'type' => 'keyword'
                    ],
                    'created_at' => [
                        'type' => 'date',
                        'format' => 'yyyy-MM-dd HH:mm:ss||yyyy-MM-dd||epoch_millis'
                    ],
                    'processed_at' => [
                        'type' => 'date',
                        'format' => 'yyyy-MM-dd HH:mm:ss||yyyy-MM-dd||epoch_millis'
                    ]
                ]
            ]
        ]
    ],

    /*
    |--------------------------------------------------------------------------
    | Search Configuration
    |--------------------------------------------------------------------------
    |
    | These settings control the default search behavior including pagination,
    | result highlighting, and fuzziness.
    |
    */

    'search' => [
        'default_operator' => 'AND',
        'fuzziness' => $_ENV['ELASTICSEARCH_FUZZINESS'] ?? 'AUTO',
        'highlight' => [
            'enabled' => true,
            'pre_tags' => ['<mark class="highlight">'],
            'post_tags' => ['</mark>'],
            'fields' => [
                'title' => new \stdClass(),
                'full_text' => new \stdClass(),
                'extracted_doc_number' => new \stdClass()
            ]
        ],
        'result_size' => [
            'max_per_page' => (int) ($_ENV['SEARCH_MAX_PER_PAGE'] ?? 100),
            'default_per_page' => (int) ($_ENV['SEARCH_DEFAULT_PER_PAGE'] ?? 10)
        ]
    ],

    /*
    |--------------------------------------------------------------------------
    | Connection Pool Configuration
    |--------------------------------------------------------------------------
    |
    | These settings control the connection pool behavior to Elasticsearch
    | including timeouts and retry mechanisms.
    |
    */

    'connection_pool' => [
        'class' => $_ENV['ELASTICSEARCH_CONNECTION_POOL'] ?? 'StaticNoPingConnectionPool',
        'randomize_hosts' => filter_var($_ENV['ELASTICSEARCH_RANDOMIZE_HOSTS'] ?? 'true', FILTER_VALIDATE_BOOLEAN),
    ],

    'retries' => (int) ($_ENV['ELASTICSEARCH_RETRIES'] ?? 3),

    'timeout' => (int) ($_ENV['ELASTICSEARCH_TIMEOUT'] ?? 30),

    'connect_timeout' => (int) ($_ENV['ELASTICSEARCH_CONNECT_TIMEOUT'] ?? 30),

    /*
    |--------------------------------------------------------------------------
    | Logging Configuration
    |--------------------------------------------------------------------------
    |
    | These settings control the logging behavior for Elasticsearch operations
    | including query logging and error reporting.
    |
    */

    'logging' => [
        'enabled' => filter_var($_ENV['ELASTICSEARCH_LOGGING_ENABLED'] ?? 'false', FILTER_VALIDATE_BOOLEAN),
        'level' => $_ENV['ELASTICSEARCH_LOG_LEVEL'] ?? 'WARNING',
        'file' => $_ENV['ELASTICSEARCH_LOG_FILE'] ?? dirname(__DIR__) . '/storage/logs/elasticsearch.log',
    ],
];