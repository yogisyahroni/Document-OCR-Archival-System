<?php

namespace App\Repositories;

use App\Models\Document;
use App\Models\DocumentCategory;
use PDO;
use PDOException;

class DocumentRepository
{
    private PDO $pdo;

    public function __construct(PDO $pdo)
    {
        $this->pdo = $pdo;
    }

    public function findById(string $id): ?Document
    {
        try {
            $stmt = $this->pdo->prepare("
                SELECT d.*, c.name as category_name 
                FROM documents d 
                LEFT JOIN document_categories c ON d.category_id = c.id 
                WHERE d.id = :id
            ");
            $stmt->bindParam(':id', $id);
            $stmt->execute();
            
            $data = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if (!$data) {
                return null;
            }
            
            return $this->mapDataToDocument($data);
        } catch (PDOException $e) {
            error_log("Database error in DocumentRepository::findById: " . $e->getMessage());
            return null;
        }
    }

    public function create(Document $document): ?Document
    {
        try {
            $stmt = $this->pdo->prepare("
                INSERT INTO documents (
                    id, 
                    uploaded_by_id, 
                    s3_path, 
                    title, 
                    description, 
                    category_id, 
                    status, 
                    extracted_doc_number, 
                    full_text_indexed, 
                    ocr_metadata, 
                    created_at, 
                    updated_at
                ) 
                VALUES (
                    :id, 
                    :uploaded_by_id, 
                    :s3_path, 
                    :title, 
                    :description, 
                    :category_id, 
                    :status, 
                    :extracted_doc_number, 
                    :full_text_indexed, 
                    :ocr_metadata, 
                    :created_at, 
                    :updated_at
                )
            ");
            
            $ocrMetadataJson = $document->ocrMetadata ? json_encode($document->ocrMetadata) : null;
            
            $stmt->bindParam(':id', $document->id);
            $stmt->bindParam(':uploaded_by_id', $document->uploadedById);
            $stmt->bindParam(':s3_path', $document->s3Path);
            $stmt->bindParam(':title', $document->title);
            $stmt->bindParam(':description', $document->description);
            $stmt->bindParam(':category_id', $document->categoryId);
            $stmt->bindParam(':status', $document->status);
            $stmt->bindParam(':extracted_doc_number', $document->extractedDocNumber);
            $stmt->bindParam(':full_text_indexed', $document->fullTextIndexed, PDO::PARAM_BOOL);
            $stmt->bindParam(':ocr_metadata', $ocrMetadataJson);
            $stmt->bindParam(':created_at', $document->createdAt);
            $stmt->bindParam(':updated_at', $document->updatedAt);
            
            $result = $stmt->execute();
            
            return $result ? $document : null;
        } catch (PDOException $e) {
            error_log("Database error in DocumentRepository::create: " . $e->getMessage());
            return null;
        }
    }

    public function update(Document $document): ?Document
    {
        try {
            $stmt = $this->pdo->prepare("
                UPDATE documents 
                SET 
                    uploaded_by_id = :uploaded_by_id,
                    s3_path = :s3_path,
                    title = :title,
                    description = :description,
                    category_id = :category_id,
                    status = :status,
                    extracted_doc_number = :extracted_doc_number,
                    full_text_indexed = :full_text_indexed,
                    ocr_metadata = :ocr_metadata,
                    processed_at = :processed_at,
                    updated_at = :updated_at
                WHERE id = :id
            ");
            
            $ocrMetadataJson = $document->ocrMetadata ? json_encode($document->ocrMetadata) : null;
            
            $stmt->bindParam(':id', $document->id);
            $stmt->bindParam(':uploaded_by_id', $document->uploadedById);
            $stmt->bindParam(':s3_path', $document->s3Path);
            $stmt->bindParam(':title', $document->title);
            $stmt->bindParam(':description', $document->description);
            $stmt->bindParam(':category_id', $document->categoryId);
            $stmt->bindParam(':status', $document->status);
            $stmt->bindParam(':extracted_doc_number', $document->extractedDocNumber);
            $stmt->bindParam(':full_text_indexed', $document->fullTextIndexed, PDO::PARAM_BOOL);
            $stmt->bindParam(':ocr_metadata', $ocrMetadataJson);
            $stmt->bindParam(':processed_at', $document->processedAt);
            $stmt->bindParam(':updated_at', $document->updatedAt);
            
            $result = $stmt->execute();
            
            return $result ? $document : null;
        } catch (PDOException $e) {
            error_log("Database error in DocumentRepository::update: " . $e->getMessage());
            return null;
        }
    }

    public function delete(string $id): bool
    {
        try {
            $stmt = $this->pdo->prepare("DELETE FROM documents WHERE id = :id");
            $stmt->bindParam(':id', $id);
            
            return $stmt->execute();
        } catch (PDOException $e) {
            error_log("Database error in DocumentRepository::delete: " . $e->getMessage());
            return false;
        }
    }

    public function findAll(int $page = 1, int $limit = 10, array $filters = []): array
    {
        try {
            $offset = ($page - 1) * $limit;
            
            // Build query with filters
            $whereConditions = [];
            $params = [];
            
            if (isset($filters['status'])) {
                $whereConditions[] = "d.status = :status";
                $params[':status'] = $filters['status'];
            }
            
            if (isset($filters['category_id'])) {
                $whereConditions[] = "d.category_id = :category_id";
                $params[':category_id'] = $filters['category_id'];
            }
            
            if (isset($filters['uploaded_by_id'])) {
                $whereConditions[] = "d.uploaded_by_id = :uploaded_by_id";
                $params[':uploaded_by_id'] = $filters['uploaded_by_id'];
            }
            
            $whereClause = !empty($whereConditions) ? "WHERE " . implode(' AND ', $whereConditions) : "";
            
            $stmt = $this->pdo->prepare("
                SELECT d.*, c.name as category_name 
                FROM documents d 
                LEFT JOIN document_categories c ON d.category_id = c.id 
                $whereClause
                ORDER BY d.created_at DESC 
                LIMIT :limit OFFSET :offset
            ");
            
            foreach ($params as $key => $value) {
                $stmt->bindParam($key, $value);
            }
            
            $stmt->bindParam(':limit', $limit, PDO::PARAM_INT);
            $stmt->bindParam(':offset', $offset, PDO::PARAM_INT);
            
            $stmt->execute();
            
            $results = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            // Get total count for pagination
            $countStmt = $this->pdo->prepare("
                SELECT COUNT(*) 
                FROM documents d 
                $whereClause
            ");
            
            foreach ($params as $key => $value) {
                $countStmt->bindParam($key, $value);
            }
            
            $countStmt->execute();
            $total = $countStmt->fetchColumn();
            
            $documents = array_map(function ($data) {
                return $this->mapDataToDocument($data);
            }, $results);
            
            return [
                'data' => $documents,
                'pagination' => [
                    'current_page' => $page,
                    'per_page' => $limit,
                    'total' => $total,
                    'total_pages' => ceil($total / $limit)
                ]
            ];
        } catch (PDOException $e) {
            error_log("Database error in DocumentRepository::findAll: " . $e->getMessage());
            return [
                'data' => [],
                'pagination' => [
                    'current_page' => $page,
                    'per_page' => $limit,
                    'total' => 0,
                    'total_pages' => 0
                ]
            ];
        }
    }

    public function search(string $query, int $userId, int $page = 1, int $limit = 10): array
    {
        try {
            $offset = ($page - 1) * $limit;
            
            // Search in title, description, and extracted document number
            $searchTerm = '%' . $query . '%';
            
            $stmt = $this->pdo->prepare("
                SELECT d.*, c.name as category_name 
                FROM documents d 
                LEFT JOIN document_categories c ON d.category_id = c.id 
                WHERE 
                    (d.title ILIKE :query OR 
                     d.description ILIKE :query OR 
                     d.extracted_doc_number ILIKE :query) 
                    AND d.uploaded_by_id = :user_id
                ORDER BY 
                    CASE 
                        WHEN d.extracted_doc_number ILIKE :query THEN 1
                        WHEN d.title ILIKE :query THEN 2
                        ELSE 3
                    END,
                    d.created_at DESC
                LIMIT :limit OFFSET :offset
            ");
            
            $stmt->bindParam(':query', $searchTerm);
            $stmt->bindParam(':user_id', $userId);
            $stmt->bindParam(':limit', $limit, PDO::PARAM_INT);
            $stmt->bindParam(':offset', $offset, PDO::PARAM_INT);
            
            $stmt->execute();
            
            $results = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            // Get total count for pagination
            $countStmt = $this->pdo->prepare("
                SELECT COUNT(*) 
                FROM documents d 
                WHERE 
                    (d.title ILIKE :query OR 
                     d.description ILIKE :query OR 
                     d.extracted_doc_number ILIKE :query) 
                    AND d.uploaded_by_id = :user_id
            ");
            
            $countStmt->bindParam(':query', $searchTerm);
            $countStmt->bindParam(':user_id', $userId);
            
            $countStmt->execute();
            $total = $countStmt->fetchColumn();
            
            $documents = array_map(function ($data) {
                return $this->mapDataToDocument($data);
            }, $results);
            
            return [
                'data' => $documents,
                'pagination' => [
                    'current_page' => $page,
                    'per_page' => $limit,
                    'total' => $total,
                    'total_pages' => ceil($total / $limit)
                ]
            ];
        } catch (PDOException $e) {
            error_log("Database error in DocumentRepository::search: " . $e->getMessage());
            return [
                'data' => [],
                'pagination' => [
                    'current_page' => $page,
                    'per_page' => $limit,
                    'total' => 0,
                    'total_pages' => 0
                ]
            ];
        }
    }

    private function mapDataToDocument(array $data): Document
    {
        $document = new Document();
        $document->id = $data['id'];
        $document->uploadedById = (int)$data['uploaded_by_id'];
        $document->s3Path = $data['s3_path'];
        $document->title = $data['title'];
        $document->description = $data['description'];
        $document->categoryId = $data['category_id'] ? (int)$data['category_id'] : null;
        $document->status = $data['status'];
        $document->extractedDocNumber = $data['extracted_doc_number'];
        $document->fullTextIndexed = (bool)$data['full_text_indexed'];
        $document->ocrMetadata = $data['ocr_metadata'] ? json_decode($data['ocr_metadata'], true) : null;
        $document->createdAt = $data['created_at'];
        $document->updatedAt = $data['updated_at'];
        $document->processedAt = $data['processed_at'];
        
        return $document;
    }
}