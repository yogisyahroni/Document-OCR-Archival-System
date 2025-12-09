<?php

namespace App\Services;

use App\Models\Document;
use App\Models\DocumentOCRJob;
use App\DTOs\CreateDocumentDTO;
use App\Repositories\DocumentRepository;
use Aws\S3\S3Client;
use PDO;
use Ramsey\Uuid\Uuid;
use Exception;

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

    public function create(CreateDocumentDTO $dto): ?Document
    {
        if (!$dto->validate()) {
            return null;
        }

        $document = new Document(
            null,
            $dto->uploadedById,
            $dto->s3Path,
            $dto->title,
            $dto->description,
            $dto->categoryId,
            'PENDING', // Initial status
            null, // No extracted doc number yet
            false, // Not indexed yet
            null, // No OCR metadata yet
            null, // Created at will be set by constructor
            null  // Updated at will be set by constructor
        );

        $savedDocument = $this->documentRepository->create($document);

        if ($savedDocument) {
            // Create OCR job
            $this->createOCRJob($savedDocument->id);
        }

        return $savedDocument;
    }

    public function findById(string $id): ?Document
    {
        return $this->documentRepository->findById($id);
    }

    public function findAll(int $page = 1, int $limit = 10, array $filters = []): array
    {
        return $this->documentRepository->findAll($page, $limit, $filters);
    }

    public function update(Document $document): ?Document
    {
        return $this->documentRepository->update($document);
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
            error_log("Failed to delete file from S3: " . $e->getMessage());
            // Continue with DB deletion even if S3 deletion fails
        }

        // Delete from database
        return $this->documentRepository->delete($id);
    }

    public function uploadFile(mixed $file, string $uploadDir = 'documents/'): ?string
    {
        try {
            // In a real implementation, this would handle the file upload
            // For now, we'll simulate the upload and return a path
            $fileName = $uploadDir . Uuid::uuid4()->toString() . '.' . $this->getFileExtension($file);
            
            // In a real implementation, we would upload the file to S3 here
            // $this->s3Client->putObject([
            //     'Bucket' => $_ENV['S3_BUCKET'],
            //     'Key' => $fileName,
            //     'Body' => fopen($file->getRealPath(), 'r'),
            //     'ACL' => 'private'
            // ]);

            return $fileName;
        } catch (Exception $e) {
            error_log("File upload error: " . $e->getMessage());
            return null;
        }
    }

    public function updateDocumentStatus(string $documentId, string $status, string $extractedDocNumber = null, array $ocrMetadata = null): bool
    {
        $document = $this->documentRepository->findById($documentId);
        
        if (!$document) {
            return false;
        }

        $document->status = $status;
        
        if ($extractedDocNumber !== null) {
            $document->extractedDocNumber = $extractedDocNumber;
        }
        
        if ($ocrMetadata !== null) {
            $document->ocrMetadata = $ocrMetadata;
        }
        
        if ($status === 'PROCESSED') {
            $document->processedAt = (new \DateTime())->format('Y-m-d H:i:s');
        }

        $document->updatedAt = (new \DateTime())->format('Y-m-d H:i:s');

        return $this->documentRepository->update($document) !== null;
    }

    private function createOCRJob(string $documentId): void
    {
        // In a real implementation, we would insert the job into a queue table
        // For now, we'll just log that a job should be created
        error_log("OCR Job created for document: {$documentId}");
    }

    private function getFileExtension(mixed $file): string
    {
        // This is a simplified version - in a real implementation,
        // you'd want to properly detect the file extension
        if (is_string($file) && pathinfo($file, PATHINFO_EXTENSION)) {
            return pathinfo($file, PATHINFO_EXTENSION);
        }
        
        // Default to pdf if we can't determine the extension
        return 'pdf';
    }

    public function searchDocuments(string $query, int $userId, int $page = 1, int $limit = 10): array
    {
        // This would integrate with Elasticsearch in a real implementation
        // For now, we'll search in the database
        return $this->documentRepository->search($query, $userId, $page, $limit);
    }
}