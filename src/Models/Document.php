<?php

namespace App\Models;

use Ramsey\Uuid\Uuid;
use DateTime;

class Document
{
    public string $id;
    public int $uploadedById;
    public string $s3Path;
    public string $title;
    public ?string $description;
    public ?int $categoryId;
    public string $status; // PENDING, PROCESSING, PROCESSED, FAILED
    public ?string $extractedDocNumber;
    public bool $fullTextIndexed;
    public ?array $ocrMetadata;
    public string $createdAt;
    public string $updatedAt;
    public ?string $processedAt;
    public ?string $fullText;
    public ?string $errorMessage;

    public function __construct(
        string $id = null,
        int $uploadedById = 0,
        string $s3Path = '',
        string $title = '',
        string $description = null,
        int $categoryId = null,
        string $status = 'PENDING',
        string $extractedDocNumber = null,
        bool $fullTextIndexed = false,
        array $ocrMetadata = null,
        string $createdAt = null,
        string $updatedAt = null,
        string $processedAt = null,
        string $fullText = null,
        string $errorMessage = null
    ) {
        $this->id = $id ?? Uuid::uuid4()->toString();
        $this->uploadedById = $uploadedById;
        $this->s3Path = $s3Path;
        $this->title = $title;
        $this->description = $description;
        $this->categoryId = $categoryId;
        $this->status = $status;
        $this->extractedDocNumber = $extractedDocNumber;
        $this->fullTextIndexed = $fullTextIndexed;
        $this->ocrMetadata = $ocrMetadata;
        $this->createdAt = $createdAt ?? (new DateTime())->format('Y-m-d H:i:s');
        $this->updatedAt = $updatedAt ?? (new DateTime())->format('Y-m-d H:i:s');
        $this->processedAt = $processedAt;
        $this->fullText = $fullText;
        $this->errorMessage = $errorMessage;
    }

    public function isValidStatus(): bool
    {
        $validStatuses = ['PENDING', 'PROCESSING', 'PROCESSED', 'FAILED'];
        return in_array(strtoupper($this->status), $validStatuses);
    }

    public function isProcessed(): bool
    {
        return $this->status === 'PROCESSED';
    }

    public function isFailed(): bool
    {
        return $this->status === 'FAILED';
    }

    public function isPending(): bool
    {
        return $this->status === 'PENDING';
    }

    public function isProcessing(): bool
    {
        return $this->status === 'PROCESSING';
    }
}