# Elasticsearch Integration & Document Search

## Ikhtisar
Dokumen ini menjelaskan integrasi Elasticsearch dalam sistem Document OCR & Archival System, yang menyediakan kemampuan pencarian teks penuh dan pencocokan nomor dokumen secara cepat.

## Arsitektur Pencarian

### Komponen Utama
- **Elasticsearch Cluster**: Mesin pencarian terdistribusi
- **Indexing Service**: Layanan untuk mengindeks dokumen ke Elasticsearch
- **Search Service**: Layanan untuk melakukan pencarian dokumen
- **Full-Text Index**: Indeks untuk pencarian teks penuh
- **Document Number Index**: Indeks khusus untuk nomor dokumen

### Alur Pencarian
```
┌─────────────────┐    ┌─────────────────┐    ┌─────────────────┐
│   Search Query  │ -> │  Search Service │ -> │  Elasticsearch  │
│ (Keyword/Doc#)  │    │  (Query Builder)│    │  (Search Engine)│
└─────────────────┘    └─────────────────┘    └─────────────────┘
         │                       │                       │
         ▼                       ▼
┌─────────────────┐    ┌─────────────────┐    ┌─────────────────┐
│ Query           │ -> │ Query           │ -> │ Search Results  │
│ Validation      │    │ Optimization    │    │ (Documents)     │
└─────────────────┘    └─────────────────┘    └─────────────────┘
         │                       │                       │
         ▼                       ▼
┌─────────────────┐    ┌─────────────────┐    ┌─────────────────┐
│ Security Check  │ -> │ Result          │ -> │ Return to       │
│ (Permissions)   │    │ Processing      │    │   Client        │
└─────────────────┘    └─────────────────┘    └─────────────────┘
```

## Konfigurasi Elasticsearch

### File Konfigurasi
```php
// config/elasticsearch.php
return [
    'hosts' => [
        env('ELASTICSEARCH_HOST', 'localhost') . ':' . env('ELASTICSEARCH_PORT', 9200)
    ],
    
    'index_prefix' => env('ELASTICSEARCH_INDEX_PREFIX', 'doc_ocr'),
    
    'indices' => [
        'documents' => [
            'name' => env('ELASTICSEARCH_INDEX_PREFIX', 'doc_ocr') . '_documents',
            'settings' => [
                'number_of_shards' => (int)env('ELASTICSEARCH_SHARDS', 1),
                'number_of_replicas' => (int)env('ELASTICSEARCH_REPLICAS', 0),
                'refresh_interval' => '1s',
                'analysis' => [
                    'analyzer' => [
                        'document_number_analyzer' => [
                            'type' => 'custom',
                            'tokenizer' => 'keyword',
                            'filter' => ['uppercase']
                        ],
                        'text_analyzer' => [
                            'type' => 'custom',
                            'tokenizer' => 'standard',
                            'filter' => ['lowercase', 'stop', 'snowball']
                        ]
                    ]
                ]
            ],
            'mappings' => [
                'properties' => [
                    'id' => [
                        'type' => 'keyword'
                    ],
                    'title' => [
                        'type' => 'text',
                        'analyzer' => 'text_analyzer',
                        'fields' => [
                            'keyword' => [
                                'type' => 'keyword'
                            ]
                        ]
                    ],
                    'extracted_doc_number' => [
                        'type' => 'text',
                        'analyzer' => 'document_number_analyzer',
                        'fields' => [
                            'keyword' => [
                                'type' => 'keyword'
                            ]
                        ]
                    ],
                    'full_text' => [
                        'type' => 'text',
                        'analyzer' => 'text_analyzer'
                    ],
                    'uploaded_by_id' => [
                        'type' => 'keyword'
                    ],
                    'status' => [
                        'type' => 'keyword'
                    ],
                    'created_at' => [
                        'type' => 'date'
                    ],
                    'ocr_metadata' => [
                        'type' => 'object'
                    ]
                ]
            ]
        ]
    ],
    
    'search_settings' => [
        'max_result_window' => 10000,
        'highlight' => [
            'pre_tags' => ['<mark>'],
            'post_tags' => ['</mark>']
        ]
    ]
];
```

### Environment Variables
```env
# Elasticsearch Configuration
ELASTICSEARCH_HOST=localhost
ELASTICSEARCH_PORT=9200
ELASTICSEARCH_INDEX_PREFIX=doc_ocr
ELASTICSEARCH_SHARDS=1
ELASTICSEARCH_REPLICAS=0
```

## Implementasi Indexing

### Indexing Service
```php
// src/Services/IndexingService.php
<?php

namespace App\Services;

use Elasticsearch\ClientBuilder;
use App\Models\Document;
use App\Exceptions\IndexingException;

class IndexingService
{
    private $client;
    private array $config;
    
    public function __construct()
    {
        $this->config = config('elasticsearch');
        
        $this->client = ClientBuilder::create()
            ->setHosts($this->config['hosts'])
            ->build();
    }
    
    public function indexDocument(Document $document): bool
    {
        try {
            $documentData = [
                'id' => $document->id,
                'title' => $document->title,
                'extracted_doc_number' => $document->extracted_doc_number,
                'full_text' => $document->ocr_metadata ? json_decode($document->ocr_metadata, true)['full_text'] ?? '' : '',
                'uploaded_by_id' => $document->uploaded_by_id,
                'status' => $document->status,
                'created_at' => $document->created_at->toISOString(),
                'ocr_metadata' => $document->ocr_metadata ? json_decode($document->ocr_metadata, true) : null
            ];
            
            $params = [
                'index' => $this->config['indices']['documents']['name'],
                'id' => $document->id,
                'body' => $documentData
            ];
            
            $response = $this->client->index($params);
            
            // Update status di database
            $document->update(['full_text_indexed' => true]);
            
            return true;
            
        } catch (\Exception $e) {
            throw new IndexingException(
                "Failed to index document {$document->id}: " . $e->getMessage(),
                0,
                $e
            );
        }
    }
    
    public function bulkIndex(array $documents): array
    {
        $bulkParams = ['body' => []];
        
        foreach ($documents as $document) {
            $documentData = [
                'id' => $document->id,
                'title' => $document->title,
                'extracted_doc_number' => $document->extracted_doc_number,
                'full_text' => $document->ocr_metadata ? json_decode($document->ocr_metadata, true)['full_text'] ?? '' : '',
                'uploaded_by_id' => $document->uploaded_by_id,
                'status' => $document->status,
                'created_at' => $document->created_at->toISOString(),
                'ocr_metadata' => $document->ocr_metadata ? json_decode($document->ocr_metadata, true) : null
            ];
            
            $bulkParams['body'][] = [
                'index' => [
                    '_index' => $this->config['indices']['documents']['name'],
                    '_id' => $document->id
                ]
            ];
            
            $bulkParams['body'][] = $documentData;
        }
        
        try {
            $responses = $this->client->bulk($bulkParams);
            
            // Update status untuk dokumen yang berhasil diindeks
            $successful = [];
            $failed = [];
            
            foreach ($responses['items'] as $index => $item) {
                $docId = $documents[$index]->id;
                
                if (isset($item['index']['result']) && $item['index']['result'] === 'created') {
                    $documents[$index]->update(['full_text_indexed' => true]);
                    $successful[] = $docId;
                } else {
                    $failed[] = [
                        'id' => $docId,
                        'error' => $item['index']['error'] ?? 'Unknown error'
                    ];
                }
            }
            
            return [
                'successful' => $successful,
                'failed' => $failed,
                'total' => count($documents)
            ];
            
        } catch (\Exception $e) {
            throw new IndexingException(
                "Bulk indexing failed: " . $e->getMessage(),
                0,
                $e
            );
        }
    }
    
    public function deleteDocument(string $documentId): bool
    {
        try {
            $params = [
                'index' => $this->config['indices']['documents']['name'],
                'id' => $documentId
            ];
            
            $this->client->delete($params);
            
            return true;
            
        } catch (\Exception $e) {
            if ($e->getCode() === 404) {
                // Dokumen tidak ditemukan di indeks, abaikan
                return true;
            }
            
            throw new IndexingException(
                "Failed to delete document {$documentId} from index: " . $e->getMessage(),
                0,
                $e
            );
        }
    }
    
    public function createIndex(): bool
    {
        $indexName = $this->config['indices']['documents']['name'];
        $settings = $this->config['indices']['documents']['settings'];
        $mappings = $this->config['indices']['documents']['mappings'];
        
        $params = [
            'index' => $indexName,
            'body' => [
                'settings' => $settings,
                'mappings' => $mappings
            ]
        ];
        
        try {
            $this->client->indices()->create($params);
            return true;
        } catch (\Exception $e) {
            if ($e->getCode() === 400) {
                // Indeks mungkin sudah ada
                return true;
            }
            
            throw new IndexingException(
                "Failed to create index {$indexName}: " . $e->getMessage(),
                0,
                $e
            );
        }
    }
}
```

## Implementasi Pencarian

### Search Service
```php
// src/Services/SearchService.php
<?php

namespace App\Services;

use Elasticsearch\ClientBuilder;
use App\Exceptions\SearchException;

class SearchService
{
    private $client;
    private array $config;
    
    public function __construct()
    {
        $this->config = config('elasticsearch');
        
        $this->client = ClientBuilder::create()
            ->setHosts($this->config['hosts'])
            ->build();
    }
    
    public function searchDocuments(array $params): array
    {
        $index = $this->config['indices']['documents']['name'];
        $searchParams = [
            'index' => $index,
            'body' => []
        ];
        
        // Bangun query berdasarkan parameter
        $query = $this->buildQuery($params);
        $searchParams['body']['query'] = $query;
        
        // Tambahkan highlight jika diminta
        if (isset($params['highlight']) && $params['highlight']) {
            $searchParams['body']['highlight'] = $this->config['search_settings']['highlight'];
        }
        
        // Tambahkan pagination
        $page = (int)($params['page'] ?? 1);
        $size = (int)($params['size'] ?? 20);
        $searchParams['body']['from'] = ($page - 1) * $size;
        $searchParams['body']['size'] = $size;
        
        // Tambahkan sorting
        if (isset($params['sort'])) {
            $searchParams['body']['sort'] = $this->parseSort($params['sort']);
        } else {
            // Default sort by relevance or date
            $searchParams['body']['sort'] = [
                '_score' => ['order' => 'desc'],
                'created_at' => ['order' => 'desc']
            ];
        }
        
        try {
            $response = $this->client->search($searchParams);
            
            return [
                'total' => $response['hits']['total']['value'],
                'documents' => $this->formatResults($response['hits']['hits']),
                'took' => $response['took'],
                'max_score' => $response['hits']['max_score'] ?? null
            ];
            
        } catch (\Exception $e) {
            throw new SearchException(
                "Search failed: " . $e->getMessage(),
                0,
                $e
            );
        }
    }
    
    private function buildQuery(array $params): array
    {
        $queries = [];
        
        // Query untuk nomor dokumen
        if (isset($params['document_number']) && !empty($params['document_number'])) {
            $queries[] = [
                'match_phrase' => [
                    'extracted_doc_number' => [
                        'query' => $params['document_number'],
                        'boost' => 10
                    ]
                ]
            ];
        }
        
        // Query teks penuh
        if (isset($params['q']) && !empty($params['q'])) {
            $queries[] = [
                'multi_match' => [
                    'query' => $params['q'],
                    'fields' => ['title^3', 'full_text^1', 'extracted_doc_number^5'],
                    'type' => 'best_fields',
                    'fuzziness' => 'AUTO',
                    'prefix_length' => 2,
                    'max_expansions' => 50
                ]
            ];
        }
        
        // Filter berdasarkan status
        if (isset($params['status']) && !empty($params['status'])) {
            $queries[] = [
                'term' => [
                    'status' => $params['status']
                ]
            ];
        }
        
        // Filter berdasarkan tanggal
        if (isset($params['date_from']) || isset($params['date_to'])) {
            $rangeQuery = ['range' => ['created_at' => []]];
            
            if (isset($params['date_from'])) {
                $rangeQuery['range']['created_at']['gte'] = $params['date_from'];
            }
            
            if (isset($params['date_to'])) {
                $rangeQuery['range']['created_at']['lte'] = $params['date_to'];
            }
            
            $queries[] = $rangeQuery;
        }
        
        // Filter berdasarkan user
        if (isset($params['user_id'])) {
            $queries[] = [
                'term' => [
                    'uploaded_by_id' => $params['user_id']
                ]
            ];
        }
        
        // Gabungkan semua query
        if (count($queries) === 1) {
            return $queries[0];
        } else {
            return [
                'bool' => [
                    'should' => $queries,
                    'minimum_should_match' => 1
                ]
            ];
        }
    }
    
    private function parseSort(string $sort): array
    {
        $sortParts = explode(',', $sort);
        $sortArray = [];
        
        foreach ($sortParts as $sortPart) {
            $direction = 'asc';
            $field = trim($sortPart);
            
            if (str_ends_with($field, ':desc')) {
                $field = substr($field, 0, -5);
                $direction = 'desc';
            } elseif (str_ends_with($field, ':asc')) {
                $field = substr($field, 0, -4);
                $direction = 'asc';
            }
            
            $sortArray[] = [
                $field => ['order' => $direction]
            ];
        }
        
        return $sortArray;
    }
    
    private function formatResults(array $hits): array
    {
        $results = [];
        
        foreach ($hits as $hit) {
            $source = $hit['_source'];
            $result = [
                'id' => $source['id'],
                'title' => $source['title'],
                'extracted_doc_number' => $source['extracted_doc_number'],
                'status' => $source['status'],
                'created_at' => $source['created_at'],
                'uploaded_by_id' => $source['uploaded_by_id'],
                'score' => $hit['_score']
            ];
            
            // Tambahkan highlight jika ada
            if (isset($hit['highlight'])) {
                $result['highlight'] = $hit['highlight'];
            }
            
            $results[] = $result;
        }
        
        return $results;
    }
    
    public function suggestDocumentNumbers(string $query, int $size = 10): array
    {
        $index = $this->config['indices']['documents']['name'];
        
        $searchParams = [
            'index' => $index,
            'body' => [
                'suggest' => [
                    'document-number-suggest' => [
                        'text' => $query,
                        'completion' => [
                            'field' => 'extracted_doc_number',
                            'size' => $size,
                            'skip_duplicates' => true
                        ]
                    ]
                ],
                'size' => 0 // Kita hanya butuh saran, bukan hasil pencarian
            ]
        ];
        
        try {
            $response = $this->client->search($searchParams);
            
            $suggestions = [];
            if (isset($response['suggest']['document-number-suggest'][0]['options'])) {
                foreach ($response['suggest']['document-number-suggest'][0]['options'] as $option) {
                    $suggestions[] = $option['text'];
                }
            }
            
            return $suggestions;
            
        } catch (\Exception $e) {
            throw new SearchException(
                "Suggestion search failed: " . $e->getMessage(),
                0,
                $e
            );
        }
    }
    
    public function getSearchStats(): array
    {
        $index = $this->config['indices']['documents']['name'];
        
        try {
            $stats = $this->client->indices()->stats(['index' => $index]);
            
            return [
                'docs_count' => $stats['indices'][$index]['primaries']['docs']['count'],
                'store_size' => $stats['indices'][$index]['primaries']['store']['size_in_bytes'],
                'search_queries' => $stats['indices'][$index]['total']['search']['query_total'],
                'search_time' => $stats['indices'][$index]['total']['search']['query_time_in_millis']
            ];
            
        } catch (\Exception $e) {
            throw new SearchException(
                "Failed to get search stats: " . $e->getMessage(),
                0,
                $e
            );
        }
    }
}
```

## API Endpoints untuk Pencarian

### Controller Pencarian
```php
// src/Controllers/SearchController.php
<?php

namespace App\Controllers;

use App\Services\SearchService;
use App\Services\IndexingService;
use App\Utils\Router;

class SearchController
{
    private SearchService $searchService;
    private IndexingService $indexingService;
    
    public function __construct()
    {
        $this->searchService = new SearchService();
        $this->indexingService = new IndexingService();
    }
    
    public function search(array $request): array
    {
        $params = [
            'q' => $request['q'] ?? '',
            'document_number' => $request['document_number'] ?? '',
            'status' => $request['status'] ?? '',
            'date_from' => $request['date_from'] ?? '',
            'date_to' => $request['date_to'] ?? '',
            'user_id' => $request['user_id'] ?? null,
            'page' => (int)($request['page'] ?? 1),
            'size' => min(100, (int)($request['size'] ?? 20)), // Batasi maksimal 100 per halaman
            'sort' => $request['sort'] ?? '',
            'highlight' => (bool)($request['highlight'] ?? false)
        ];
        
        // Validasi parameter
        if (empty($params['q']) && empty($params['document_number'])) {
            return [
                'success' => false,
                'error' => 'Query parameter (q or document_number) is required'
            ];
        }
        
        try {
            $results = $this->searchService->searchDocuments($params);
            
            return [
                'success' => true,
                'data' => $results
            ];
            
        } catch (\Exception $e) {
            return [
                'success' => false,
                'error' => $e->getMessage()
            ];
        }
    }
    
    public function suggest(array $request): array
    {
        $query = $request['q'] ?? '';
        
        if (empty($query)) {
            return [
                'success' => false,
                'error' => 'Query parameter is required'
            ];
        }
        
        try {
            $suggestions = $this->searchService->suggestDocumentNumbers($query);
            
            return [
                'success' => true,
                'data' => [
                    'query' => $query,
                    'suggestions' => $suggestions
                ]
            ];
            
        } catch (\Exception $e) {
            return [
                'success' => false,
                'error' => $e->getMessage()
            ];
        }
    }
    
    public function stats(): array
    {
        try {
            $stats = $this->searchService->getSearchStats();
            
            return [
                'success' => true,
                'data' => $stats
            ];
            
        } catch (\Exception $e) {
            return [
                'success' => false,
                'error' => $e->getMessage()
            ];
        }
    }
}
```

## Optimasi Pencarian

### Query Optimization
```php
// Optimasi query untuk pencarian nomor dokumen yang akurat
private function buildOptimizedDocumentNumberQuery(string $docNumber): array
{
    return [
        'bool' => [
            'should' => [
                // Exact match
                [
                    'term' => [
                        'extracted_doc_number.keyword' => [
                            'value' => $docNumber,
                            'boost' => 10
                        ]
                    ]
                ],
                // Match phrase untuk fleksibilitas
                [
                    'match_phrase' => [
                        'extracted_doc_number' => [
                            'query' => $docNumber,
                            'boost' => 5
                        ]
                    ]
                ],
                // Fuzzy match untuk kesalahan ketik
                [
                    'match' => [
                        'extracted_doc_number' => [
                            'query' => $docNumber,
                            'fuzziness' => 'AUTO',
                            'prefix_length' => 3,
                            'boost' => 2
                        ]
                    ]
                ]
            ],
            'minimum_should_match' => 1
        ]
    ];
}
```

### Indexing Optimization
```php
// Gunakan bulk indexing untuk efisiensi
public function optimizedBulkIndex(array $documents): array
{
    $chunkSize = 1000; // Ukuran chunk untuk bulk indexing
    $results = [
        'successful' => [],
        'failed' => [],
        'total' => 0
    ];
    
    $chunks = array_chunk($documents, $chunkSize);
    
    foreach ($chunks as $chunk) {
        $chunkResult = $this->bulkIndex($chunk);
        
        $results['successful'] = array_merge($results['successful'], $chunkResult['successful']);
        $results['failed'] = array_merge($results['failed'], $chunkResult['failed']);
        $results['total'] += $chunkResult['total'];
    }
    
    return $results;
}
```

## Monitoring dan Observability

### Metrik Pencarian
- **Search Latency**: Waktu respons rata-rata untuk pencarian
- **Query Rate**: Jumlah query per detik
- **Hit Rate**: Persentase query yang mengembalikan hasil
- **Indexing Performance**: Waktu dan throughput untuk indexing dokumen
- **Resource Usage**: Penggunaan memory dan CPU oleh Elasticsearch

### Logging
```php
// Log aktivitas pencarian
log_info('Search Query Executed', [
    'query' => $params['q'] ?? '',
    'document_number' => $params['document_number'] ?? '',
    'user_id' => $params['user_id'] ?? null,
    'page' => $params['page'] ?? 1,
    'size' => $params['size'] ?? 20,
    'took_ms' => $response['took'] ?? 0,
    'total_hits' => $response['total'] ?? 0,
    'timestamp' => date('c')
]);
```

## Kesimpulan

Integrasi Elasticsearch dalam sistem Document OCR & Archival System menyediakan kemampuan pencarian teks penuh dan pencocokan nomor dokumen yang cepat dan akurat. Dengan konfigurasi yang tepat dan implementasi layanan pencarian yang efisien, sistem dapat menangani volume besar dokumen dengan kinerja tinggi.