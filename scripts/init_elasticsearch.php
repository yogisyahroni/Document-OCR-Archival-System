<?php

/**
 * Elasticsearch Initialization Script
 *
 * Script ini digunakan untuk menginisialisasi koneksi ke Elasticsearch
 * dan membuat/memperbarui indeks dokumen sesuai dengan konfigurasi yang ditentukan.
 *
 * Pastikan dependency elasticsearch/elasticsearch telah terinstal melalui Composer.
 *
 * @package DocumentOCR
 * @author Your Name <your.email@example.com>
 * @version 1.0.0
 */

require_once __DIR__ . '/../vendor/autoload.php';

use App\Config\Config;
use Elasticsearch\ClientBuilder;

// Pastikan dependency elasticsearch/elasticsearch terinstal sebelum melanjutkan
if (!class_exists('Elasticsearch\ClientBuilder')) {
    throw new RuntimeException('Elasticsearch\ClientBuilder class not found. Please run "composer install" to install dependencies.');
}

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

// Validate required environment variables for Elasticsearch
$requiredEnvVars = [
    'ELASTICSEARCH_HOST',
    'DOCUMENTS_INDEX_NAME'
];

foreach ($requiredEnvVars as $envVar) {
    if (!isset($_ENV[$envVar])) {
        echo "Warning: Environment variable {$envVar} is not set. Using default value where applicable.\n";
    }
}

// Ensure Elasticsearch\ClientBuilder is available
if (!class_exists('Elasticsearch\ClientBuilder')) {
    echo "Error: Elasticsearch\ClientBuilder class not found. Please run 'composer install' to install dependencies.\n";
    exit(1);
}

/**
 * @var \Elasticsearch\Client $client Elasticsearch client instance
 */
// Create Elasticsearch client
$hosts = [$_ENV['ELASTICSEARCH_HOST'] ?? 'localhost:9200'];
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
        
        // Optionally update mappings if needed
        $updateMapping = $_ENV['UPDATE_EXISTING_MAPPING'] ?? 'false';
        if (filter_var($updateMapping, FILTER_VALIDATE_BOOLEAN)) {
            echo "Updating mapping for {$indexName}...\n";
            $client->indices()->putMapping([
                'index' => $indexName,
                'body' => [
                    'properties' => $indexMappings
                ]
            ]);
            echo "Mapping updated successfully.\n";
        }
    } else {
        // Create the index
        echo "Creating index {$indexName}...\n";
        
        $params = [
            'index' => $indexName,
            'body' => [
                'settings' => [
                    'number_of_shards' => (int) ($_ENV['DOCUMENTS_SHARDS'] ?? 1),
                    'number_of_replicas' => (int) ($_ENV['DOCUMENTS_REPLICAS'] ?? 0),
                    'refresh_interval' => $_ENV['DOCUMENTS_REFRESH_INTERVAL'] ?? '1s',
                    'index.max_result_window' => (int) ($_ENV['DOCUMENTS_MAX_RESULT_WINDOW'] ?? 10000),
                ],
                'mappings' => [
                    'properties' => $indexMappings
                ]
            ]
        ];
        
        $response = $client->indices()->create($params);
        
        if ($response['acknowledged']) {
            echo "Index {$indexName} created successfully.\n";
        } else {
            echo "Failed to create index {$indexName}.\n";
        }
    }
    
    echo "Elasticsearch initialization completed.\n";
} catch (Exception $e) {
    echo "Error initializing Elasticsearch: " . $e->getMessage() . "\n";
    exit(1);
}