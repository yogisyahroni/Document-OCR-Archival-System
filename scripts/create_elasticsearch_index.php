<?php

require_once __DIR__ . '/../vendor/autoload.php';

use App\Config\Config;
use Elastic\Elasticsearch\ClientBuilder;

// Load environment variables
$dotenv = Dotenv\Dotenv::createImmutable(__DIR__ . '/../');
$dotenv->safeLoad();

// Initialize configuration
$configFiles = glob(__DIR__ . '/../config/*.php');
foreach ($configFiles as $configFile) {
    $configName = basename($configFile, '.php');
    $configData = include $configFile;
    Config::set($configName, $configData);
}

// Create Elasticsearch client configuration
$hosts = [$_ENV['ELASTICSEARCH_HOST'] ?? 'localhost:9200'];

// Initialize Elasticsearch client
$client = ClientBuilder::create()
    ->setHosts($hosts)
    ->build();

// Define the documents index configuration
$indexName = $_ENV['DOCUMENTS_INDEX_NAME'] ?? 'documents';
$indexSettings = [
    'number_of_shards' => (int) ($_ENV['DOCUMENTS_SHARDS'] ?? 1),
    'number_of_replicas' => (int) ($_ENV['DOCUMENTS_REPLICAS'] ?? 0),
    'refresh_interval' => $_ENV['DOCUMENTS_REFRESH_INTERVAL'] ?? '1s',
    'index.max_result_window' => (int) ($_ENV['DOCUMENTS_MAX_RESULT_WINDOW'] ?? 10000),
];

$indexMappings = [
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
];

try {
    // Check if index already exists
    if ($client->indices()->exists(['index' => $indexName])) {
        echo "Index {$indexName} already exists.\n";
        
        // Update mappings if needed
        echo "Updating mapping for {$indexName}...\n";
        $client->indices()->putMapping([
            'index' => $indexName,
            'body' => [
                'properties' => $indexMappings['properties']
            ]
        ]);
        echo "Mapping updated successfully.\n";
    } else {
        // Create the index
        echo "Creating index {$indexName}...\n";
        
        $params = [
            'index' => $indexName,
            'body' => [
                'settings' => $indexSettings,
                'mappings' => $indexMappings
            ]
        ];
        
        $response = $client->indices()->create($params);
        
        if ($response['acknowledged']) {
            echo "Index {$indexName} created successfully.\n";
        } else {
            echo "Failed to create index {$indexName}.\n";
            exit(1);
        }
    }
    
    echo "Elasticsearch index initialization completed.\n";
} catch (Exception $e) {
    echo "Error initializing Elasticsearch index: " . $e->getMessage() . "\n";
    exit(1);
}