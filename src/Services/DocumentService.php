<?php

namespace App\Services;

use App\Models\Document;
use App\Models\DocumentOCRJob;
use App\Repositories\DocumentRepository;
use Aws\S3\S3Client;
use PDO;
use Ramsey\Uuid\Uuid;
use App\DTOs\CreateDocumentDTO;
use Psr\Http\Message\UploadedFileInterface;
use Exception;
use Elastic\Elasticsearch\Client;
use Elastic\Elasticsearch\ClientBuilder;
use Predis\Client as Predis;

class DocumentService
{
    private DocumentRepository $documentRepository;
    private S3Client $s3Client;
    private PDO $pdo;

    public function __construct(
        DocumentRepository $documentRepository,
        S3Client $s3Client,
        PDO $pdo
    ) {
        $this->documentRepository = $documentRepository;
        $this->s3Client = $s3Client;
        $this->pdo = $pdo;
    }

    public function create(CreateDocumentDTO $dto, UploadedFileInterface $file): ?Document
    {
        if (!$dto->validate()) {
            return null;
        }

        // Upload file to S3
        $s3Path = $this->uploadFileToS3($file, $dto->uploadedById);
        if (!$s3Path) {
            return null;
        }

        // Create document record
        $document = new Document(
            null, // Let the constructor generate UUID
            $dto->uploadedById,
            $s3Path,
            $dto->title,
            $dto->description,
            $dto->categoryId,
            'PENDING', // Initial status
            null, // No extracted doc number yet
            false, // Not indexed yet
            null, // No OCR metadata yet
            null, // Let constructor set created_at
            null  // Let constructor set updated_at
        );

        $result = $this->documentRepository->create($document);

        if ($result) {
            // Create OCR job
            $this->createOCRJob($result->id, $s3Path);
        }

        return $result;
    }

    private function uploadFileToS3(UploadedFileInterface $file, int $uploadedById): ?string
    {
        try {
            $extension = pathinfo($file->getClientFilename(), PATHINFO_EXTENSION);
            $fileName = 'documents/' . $uploadedById . '/' . Uuid::uuid4()->toString() . '.' . $extension;
            
            // Move uploaded file to temporary location to get file handle
            $tempPath = sys_get_temp_dir() . '/' . basename($fileName);
            $file->moveTo($tempPath);
            
            $result = $this->s3Client->putObject([
                'Bucket' => $_ENV['S3_BUCKET'],
                'Key' => $fileName,
                'Body' => fopen($tempPath, 'r'),
                'ACL' => 'private',
                'ContentType' => $file->getClientMediaType()
            ]);

            // Clean up temporary file
            unlink($tempPath);

            return $fileName;
        } catch (Exception $e) {
            error_log("S3 upload error: " . $e->getMessage());
            return null;
        }
    }

    private function createOCRJob(string $documentId, string $s3Path): void
    {
        // In a real implementation, we would add the job to a queue
        // For now, we'll simulate by creating a record in a jobs table
        $job = new DocumentOCRJob(
            null, // Let constructor generate UUID
            $documentId,
            'QUEUED',
            0, // attempts
            3, // max attempts
            null, // error message
            null, // started at
            null, // completed at
            null, // created at
            null  // updated at
        );
        
        // Save job to repository
        // In a real implementation, this would go to a queue system like Redis/Predis
        $this->saveOCRJobToQueue($job);
    }

    /**
     * Save OCR job to Redis queue
     * Note: Requires predis/predis package to be installed
     *
     * @param DocumentOCRJob $job
     * @return bool
     */
    private function saveOCRJobToQueue(DocumentOCRJob $job): bool
    {
        // This would normally interface with a queue system
        // For now, we'll create a simple implementation that stores in Redis/Predis
        if (!class_exists('Predis\Client')) {
            error_log("Predis class not found. Please install predis/predis package.");
            return false;
        }
        
        try {
            $options = [
                'parameters' => [
                    'database' => (int)($_ENV['REDIS_DB'] ?? 0),
                ]
            ];
            
            if ($_ENV['REDIS_PASSWORD']) {
                $options['parameters']['password'] = $_ENV['REDIS_PASSWORD'];
            }
            
            $dsn = $_ENV['REDIS_HOST'] ?? 'localhost';
            $port = (int)($_ENV['REDIS_PORT'] ?? 6379);
            $host = "tcp://{$dsn}:{$port}";
            
            $redis = new Predis([$host], $options);
        } catch (Exception $e) {
            error_log("Redis connection error: " . $e->getMessage());
            return false;
        }
        
        $jobData = [
            'id' => $job->id,
            'document_id' => $job->documentId,
            'status' => $job->status,
            'attempts' => $job->attempts,
            'max_attempts' => $job->maxAttempts,
            'error_message' => $job->errorMessage,
            'created_at' => $job->createdAt,
            'updated_at' => $job->updatedAt
        ];
        
        $result = $redis->lpush('ocr_processing_queue', json_encode($jobData));
        
        return $result !== false;
    }

    public function findById(string $id): ?Document
    {
        return $this->documentRepository->findById($id);
    }

    public function updateDocumentStatus(string $documentId, string $status, string $errorMessage = null): bool
    {
        $document = $this->documentRepository->findById($documentId);
        
        if (!$document) {
            return false;
        }

        $document->status = $status;
        $document->updatedAt = date('Y-m-d H:i:s');
        
        if ($status === 'PROCESSED') {
            $document->processedAt = date('Y-m-d H:i:s');
        } elseif ($status === 'FAILED' && $errorMessage) {
            $document->errorMessage = $errorMessage;
        }

        return $this->documentRepository->update($document) !== null;
    }

    public function updateDocumentWithOCRResults(string $documentId, string $fullText, string $extractedDocNumber = null, array $ocrMetadata = null): bool
    {
        $document = $this->documentRepository->findById($documentId);
        
        if (!$document) {
            return false;
        }

        $document->fullText = $fullText;
        $document->extractedDocNumber = $extractedDocNumber;
        $document->ocrMetadata = $ocrMetadata;
        $document->fullTextIndexed = true;
        $document->status = 'PROCESSED';
        $document->processedAt = date('Y-m-d H:i:s');
        $document->updatedAt = date('Y-m-d H:i:s');

        $result = $this->documentRepository->update($document);
        
        if ($result) {
            // Index to Elasticsearch after updating document
            $this->indexToSearchEngine($documentId);
        }

        return $result !== null;
    }

    public function indexToSearchEngine(string $documentId): bool
    {
        // In a real implementation, we would index to Elasticsearch
        // For now, we'll create a placeholder method
        $document = $this->documentRepository->findById($documentId);
        
        if (!$document) {
            return false;
        }

        try {
            // Check if Elasticsearch client exists
            if (!class_exists('\Elastic\Elasticsearch\ClientBuilder')) {
                error_log("Elastic\Elasticsearch\ClientBuilder class not found. Please install elasticsearch/elasticsearch package.");
                return false;
            }
            
            // Initialize Elasticsearch client for version 8.x
            $config = [
                'hosts' => [$_ENV['ELASTICSEARCH_HOST'] ?? 'localhost:9200']
            ];
            
            $client = \Elastic\Elasticsearch\ClientBuilder::create()
                ->setHosts($config['hosts'])
                ->build();

            // Prepare document for indexing
            $documentData = [
                'id' => $document->id,
                'title' => $document->title,
                'description' => $document->description,
                'extracted_doc_number' => $document->extractedDocNumber,
                'full_text' => $document->fullText ?? '',
                'category_id' => $document->categoryId,
                'uploaded_by_id' => $document->uploadedById,
                'status' => $document->status,
                'created_at' => $document->createdAt,
                'processed_at' => $document->processedAt
            ];

            $params = [
                'index' => $_ENV['ELASTICSEARCH_INDEX'] ?? 'documents',
                'id' => $document->id,
                'body' => $documentData
            ];

            $response = $client->index($params);
            
            return $response['result'] === 'created' || $response['result'] === 'updated';
        } catch (Exception $e) {
            error_log("Elasticsearch indexing error: " . $e->getMessage());
            return false;
        }
    }

    public function searchDocuments(string $query, int $userId, int $page = 1, int $limit = 10): array
    {
        try {
            // Check if Elasticsearch client exists
            if (!class_exists('\Elastic\Elasticsearch\ClientBuilder')) {
                error_log("Elastic\Elasticsearch\ClientBuilder class not found. Please install elasticsearch/elasticsearch package.");
                return [
                    'results' => [],
                    'total' => 0,
                    'page' => $page,
                    'limit' => $limit,
                    'pages' => 0
                ];
            }
            
            // Initialize Elasticsearch client for version 8.x
            $config = [
                'hosts' => [$_ENV['ELASTICSEARCH_HOST'] ?? 'localhost:9200']
            ];
            
            $client = \Elastic\Elasticsearch\ClientBuilder::create()
                ->setHosts($config['hosts'])
                ->build();

            // Prepare search query
            $params = [
                'index' => $_ENV['ELASTICSEARCH_INDEX'] ?? 'documents',
                'from' => ($page - 1) * $limit,
                'size' => $limit,
                'body' => [
                    'query' => [
                        'bool' => [
                            'should' => [
                                ['match' => ['title' => $query]],
                                ['match' => ['extracted_doc_number' => $query]],
                                ['match' => ['full_text' => $query]],
                            ],
                            'filter' => [
                                ['term' => ['uploaded_by_id' => $userId]]
                            ]
                        ]
                    ],
                    'highlight' => [
                        'fields' => [
                            'title' => new \stdClass(),
                            'extracted_doc_number' => new \stdClass(),
                            'full_text' => new \stdClass()
                        ]
                    ]
                ]
            ];

            $response = $client->search($params);
            
            $results = [];
            foreach ($response['hits']['hits'] as $hit) {
                $doc = $hit['_source'];
                $doc['id'] = $hit['_id'];
                $doc['score'] = $hit['_score'];
                if (isset($hit['highlight'])) {
                    $doc['highlight'] = $hit['highlight'];
                }
                $results[] = $doc;
            }
            
            return [
                'results' => $results,
                'total' => $response['hits']['total']['value'],
                'page' => $page,
                'limit' => $limit,
                'pages' => ceil($response['hits']['total']['value'] / $limit)
            ];
        } catch (Exception $e) {
            error_log("Document search error: " . $e->getMessage());
            return [
                'results' => [],
                'total' => 0,
                'page' => $page,
                'limit' => $limit,
                'pages' => 0
            ];
        }
    }

    public function delete(string $id): bool
    {
        $document = $this->documentRepository->findById($id);
        
        if (!$document) {
            return false;
        }

        // Delete from S3 first
        try {
            $this->s3Client->deleteObject([
                'Bucket' => $_ENV['S3_BUCKET'],
                'Key' => $document->s3Path
            ]);
        } catch (Exception $e) {
            // Log error but continue with DB deletion
            error_log("Failed to delete file from S3: " . $e->getMessage());
        }

        // Delete from database
        $result = $this->documentRepository->delete($id);
        
        if ($result) {
            // Delete from Elasticsearch
            $this->removeFromSearchEngine($id);
        }
        
        return $result;
    }

    public function removeFromSearchEngine(string $documentId): bool
    {
        try {
            // Check if Elasticsearch client exists
            if (!class_exists('\Elastic\Elasticsearch\ClientBuilder')) {
                error_log("Elastic\Elasticsearch\ClientBuilder class not found. Please install elasticsearch/elasticsearch package.");
                return false;
            }
            
            // Initialize Elasticsearch client for version 8.x
            $config = [
                'hosts' => [$_ENV['ELASTICSEARCH_HOST'] ?? 'localhost:9200']
            ];
            
            $client = \Elastic\Elasticsearch\ClientBuilder::create()
                ->setHosts($config['hosts'])
                ->build();

            $params = [
                'index' => $_ENV['ELASTICSEARCH_INDEX'] ?? 'documents',
                'id' => $documentId
            ];

            $response = $client->delete($params);
            
            return $response['result'] === 'deleted';
        } catch (Exception $e) {
            if ($e->getCode() !== 404) {
                // Only log if it's not a "not found" error
                error_log("Elasticsearch deletion error: " . $e->getMessage());
            }
            return false;
        }
    }
}