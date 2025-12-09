<?php

namespace App\Models;

use Ramsey\Uuid\Uuid;
use DateTime;

class DocumentOCRJob
{
    public string $id;
    public string $documentId;
    public string $status; // QUEUED, PROCESSING, COMPLETED, FAILED
    public int $attempts;
    public int $maxAttempts;
    public ?string $errorMessage;
    public ?string $startedAt;
    public ?string $completedAt;
    public string $createdAt;
    public string $updatedAt;

    public function __construct(
        string $id = null,
        string $documentId = '',
        string $status = 'QUEUED',
        int $attempts = 0,
        int $maxAttempts = 3,
        string $errorMessage = null,
        string $startedAt = null,
        string $completedAt = null,
        string $createdAt = null,
        string $updatedAt = null
    ) {
        $this->id = $id ?? Uuid::uuid4()->toString();
        $this->documentId = $documentId;
        $this->status = $status;
        $this->attempts = $attempts;
        $this->maxAttempts = $maxAttempts;
        $this->errorMessage = $errorMessage;
        $this->startedAt = $startedAt;
        $this->completedAt = $completedAt;
        $this->createdAt = $createdAt ?? (new DateTime())->format('Y-m-d H:i:s');
        $this->updatedAt = $updatedAt ?? (new DateTime())->format('Y-m-d H:i:s');
    }

    public function isValidStatus(): bool
    {
        $validStatuses = ['QUEUED', 'PROCESSING', 'COMPLETED', 'FAILED'];
        return in_array(strtoupper($this->status), $validStatuses);
    }

    public function isCompleted(): bool
    {
        return $this->status === 'COMPLETED';
    }

    public function isFailed(): bool
    {
        return $this->status === 'FAILED';
    }

    public function isQueued(): bool
    {
        return $this->status === 'QUEUED';
    }

    public function isProcessing(): bool
    {
        return $this->status === 'PROCESSING';
    }

    public function canRetry(): bool
    {
        return $this->attempts < $this->maxAttempts;
    }

    public function incrementAttempt(): void
    {
        $this->attempts++;
        $this->updatedAt = (new DateTime())->format('Y-m-d H:i:s');
    }
}