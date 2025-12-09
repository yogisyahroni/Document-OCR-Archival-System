<?php

use App\Config\Config;
use PDO;

class CreateInitialSchema
{
    private PDO $pdo;

    public function __construct(PDO $pdo)
    {
        $this->pdo = $pdo;
    }

    public function up(): void
    {
        // Create users table
        $this->createUsersTable();
        
        // Create document_categories table
        $this->createDocumentCategoriesTable();
        
        // Create documents table
        $this->createDocumentsTable();
        
        // Create document_ocr_jobs table
        $this->createDocumentOCRJobsTable();
        
        // Create failed_jobs table
        $this->createFailedJobsTable();
        
        // Create migrations table
        $this->createMigrationsTable();
    }

    private function createUsersTable(): void
    {
        $sql = "
            CREATE TABLE IF NOT EXISTS users (
                id BIGSERIAL PRIMARY KEY,
                email VARCHAR(255) UNIQUE NOT NULL,
                password_hash VARCHAR(255) NOT NULL,
                role VARCHAR(50) DEFAULT 'user',
                permissions JSON DEFAULT '[]',
                is_active BOOLEAN DEFAULT TRUE,
                last_login_at TIMESTAMPTZ NULL,
                created_at TIMESTAMPTZ NOT NULL DEFAULT NOW(),
                updated_at TIMESTAMPTZ NOT NULL DEFAULT NOW()
            );
            
            CREATE INDEX IF NOT EXISTS idx_users_email ON users(email);
            CREATE INDEX IF NOT EXISTS idx_users_role ON users(role);
        ";
        
        $this->pdo->exec($sql);
    }

    private function createDocumentCategoriesTable(): void
    {
        $sql = "
            CREATE TABLE IF NOT EXISTS document_categories (
                id SERIAL PRIMARY KEY,
                name VARCHAR(100) NOT NULL,
                description TEXT,
                created_at TIMESTAMPTZ NOT NULL DEFAULT NOW(),
                updated_at TIMESTAMPTZ NOT NULL DEFAULT NOW()
            );
            
            CREATE INDEX IF NOT EXISTS idx_document_categories_name ON document_categories(name);
        ";
        
        $this->pdo->exec($sql);
    }

    private function createDocumentsTable(): void
    {
        $sql = "
            CREATE TABLE IF NOT EXISTS documents (
                id UUID PRIMARY KEY DEFAULT gen_random_uuid(),
                uploaded_by_id BIGINT NOT NULL,
                s3_path TEXT NOT NULL UNIQUE,
                title VARCHAR(255) NOT NULL,
                description TEXT,
                category_id INTEGER,
                status VARCHAR(20) NOT NULL DEFAULT 'PENDING',
                extracted_doc_number VARCHAR(100),
                full_text_indexed BOOLEAN NOT NULL DEFAULT FALSE,
                ocr_metadata JSONB,
                created_at TIMESTAMPTZ NOT NULL DEFAULT NOW(),
                updated_at TIMESTAMPTZ NOT NULL DEFAULT NOW(),
                processed_at TIMESTAMPTZ NULL,
                
                CONSTRAINT fk_documents_user FOREIGN KEY (uploaded_by_id) REFERENCES users(id) ON DELETE RESTRICT,
                CONSTRAINT fk_documents_category FOREIGN KEY (category_id) REFERENCES document_categories(id) ON DELETE SET NULL
            );
            
            CREATE INDEX IF NOT EXISTS idx_documents_status ON documents(status);
            CREATE INDEX IF NOT EXISTS idx_documents_extracted_doc_number ON documents(extracted_doc_number);
            CREATE INDEX IF NOT EXISTS idx_documents_uploaded_by_id ON documents(uploaded_by_id);
            CREATE INDEX IF NOT EXISTS idx_documents_category_id ON documents(category_id);
            CREATE INDEX IF NOT EXISTS idx_documents_created_at ON documents(created_at);
            CREATE INDEX IF NOT EXISTS idx_documents_processed_at ON documents(processed_at);
            
            CREATE INDEX IF NOT EXISTS idx_documents_doc_number_status ON documents(extracted_doc_number, status);
        ";
        
        $this->pdo->exec($sql);
    }

    private function createDocumentOCRJobsTable(): void
    {
        $sql = "
            CREATE TABLE IF NOT EXISTS document_ocr_jobs (
                id UUID PRIMARY KEY DEFAULT gen_random_uuid(),
                document_id UUID NOT NULL,
                status VARCHAR(20) NOT NULL DEFAULT 'QUEUED',
                attempts INTEGER NOT NULL DEFAULT 0,
                max_attempts INTEGER NOT NULL DEFAULT 3,
                error_message TEXT,
                started_at TIMESTAMPTZ NULL,
                completed_at TIMESTAMPTZ NULL,
                created_at TIMESTAMPTZ NOT NULL DEFAULT NOW(),
                updated_at TIMESTAMPTZ NOT NULL DEFAULT NOW(),
                
                CONSTRAINT fk_document_ocr_jobs_document FOREIGN KEY (document_id) REFERENCES documents(id) ON DELETE CASCADE
            );
            
            CREATE INDEX IF NOT EXISTS idx_document_ocr_jobs_status ON document_ocr_jobs(status);
            CREATE INDEX IF NOT EXISTS idx_document_ocr_jobs_document_id ON document_ocr_jobs(document_id);
            CREATE INDEX IF NOT EXISTS idx_document_ocr_jobs_attempts ON document_ocr_jobs(attempts);
        ";
        
        $this->pdo->exec($sql);
    }

    private function createFailedJobsTable(): void
    {
        $sql = "
            CREATE TABLE IF NOT EXISTS failed_jobs (
                id UUID PRIMARY KEY DEFAULT gen_random_uuid(),
                connection TEXT NOT NULL,
                queue TEXT NOT NULL,
                payload TEXT NOT NULL,
                exception TEXT NOT NULL,
                failed_at TIMESTAMPTZ NOT NULL DEFAULT NOW()
            );
            
            CREATE INDEX IF NOT EXISTS idx_failed_jobs_failed_at ON failed_jobs(failed_at);
        ";
        
        $this->pdo->exec($sql);
    }

    private function createMigrationsTable(): void
    {
        $sql = "
            CREATE TABLE IF NOT EXISTS migrations (
                id SERIAL PRIMARY KEY,
                migration VARCHAR(255) NOT NULL,
                batch INTEGER NOT NULL
            );
        ";
        
        $this->pdo->exec($sql);
    }

    public function down(): void
    {
        // Drop tables in reverse order to respect foreign key constraints
        $this->pdo->exec("DROP TABLE IF EXISTS document_ocr_jobs CASCADE;");
        $this->pdo->exec("DROP TABLE IF EXISTS documents CASCADE;");
        $this->pdo->exec("DROP TABLE IF EXISTS document_categories CASCADE;");
        $this->pdo->exec("DROP TABLE IF EXISTS users CASCADE;");
        $this->pdo->exec("DROP TABLE IF EXISTS failed_jobs CASCADE;");
        $this->pdo->exec("DROP TABLE IF EXISTS migrations CASCADE;");
    }
}