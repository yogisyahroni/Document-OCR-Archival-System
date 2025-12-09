<?php

namespace App\DTOs;

use Respect\Validation\Validator as v;

class UpdateDocumentStatusDTO
{
    public string $documentId;
    public string $status;
    public ?string $errorMessage;

    public function __construct(
        string $documentId,
        string $status,
        string $errorMessage = null
    ) {
        $this->documentId = $documentId;
        $this->status = $status;
        $this->errorMessage = $errorMessage;
    }

    public function validate(): bool
    {
        $validStatuses = ['PENDING', 'PROCESSING', 'PROCESSED', 'FAILED'];
        
        return v::stringType()->length(1, 255)->validate($this->documentId) &&
               in_array(strtoupper($this->status), $validStatuses);
    }

    public function getValidationErrors(): array
    {
        $errors = [];
        
        if (!v::stringType()->length(1, 255)->validate($this->documentId)) {
            $errors['document_id'] = 'Document ID must be a string between 1 and 255 characters';
        }
        
        $validStatuses = ['PENDING', 'PROCESSING', 'PROCESSED', 'FAILED'];
        if (!in_array(strtoupper($this->status), $validStatuses)) {
            $errors['status'] = 'Status must be one of: ' . implode(', ', $validStatuses);
        }
        
        return $errors;
    }
}