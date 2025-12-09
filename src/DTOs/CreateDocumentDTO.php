<?php

namespace App\DTOs;

use Respect\Validation\Validator as v;
use App\Exceptions\ValidationException;

class CreateDocumentDTO
{
    public int $uploadedById;
    public string $s3Path;
    public string $title;
    public ?string $description;
    public ?int $categoryId;
    public array $validationErrors = [];

    public function __construct(
        int $uploadedById,
        string $s3Path,
        string $title,
        ?string $description = null,
        ?int $categoryId = null
    ) {
        $this->uploadedById = $uploadedById;
        $this->s3Path = $s3Path;
        $this->title = $title;
        $this->description = $description;
        $this->categoryId = $categoryId;
    }

    public function validate(): bool
    {
        $this->validationErrors = [];

        // Validate title
        if (!v::stringType()->length(1, 255)->validate($this->title)) {
            $this->validationErrors['title'] = 'Title must be a string between 1 and 255 characters.';
        }

        // Validate description if provided
        if ($this->description !== null && !v::stringType()->length(0, 1000)->validate($this->description)) {
            $this->validationErrors['description'] = 'Description must be a string up to 1000 characters.';
        }

        // Validate category ID if provided
        if ($this->categoryId !== null && !v::intVal()->min(1)->validate($this->categoryId)) {
            $this->validationErrors['category_id'] = 'Category ID must be a positive integer.';
        }

        // Validate uploaded by ID
        if (!v::intVal()->min(1)->validate($this->uploadedById)) {
            $this->validationErrors['uploaded_by_id'] = 'Uploaded by ID must be a positive integer.';
        }

        // Validate S3 path
        if (!v::stringType()->notEmpty()->validate($this->s3Path)) {
            $this->validationErrors['s3_path'] = 'S3 path is required.';
        }

        return empty($this->validationErrors);
    }

    public function isValid(): bool
    {
        return $this->validate();
    }

    public function getValidationErrors(): array
    {
        return $this->validationErrors;
    }

    public static function fromArray(array $data): self
    {
        $dto = new self(
            (int)($data['uploaded_by_id'] ?? 0),
            $data['s3_path'] ?? '',
            $data['title'] ?? '',
            $data['description'] ?? null,
            isset($data['category_id']) ? (int)$data['category_id'] : null
        );

        if (!$dto->isValid()) {
            throw new ValidationException('Invalid data for CreateDocumentDTO', $dto->getValidationErrors());
        }

        return $dto;
    }
}