<?php

namespace App\DTOs;

use Respect\Validation\Validator as v;

class CreateDocumentDTO
{
    public string $title;
    public ?string $description;
    public ?int $categoryId;
    public int $uploadedById;
    public string $s3Path;
    public array $validationErrors = [];

    public function __construct(
        string $title,
        string $description = null,
        int $categoryId = null,
        int $uploadedById,
        string $s3Path
    ) {
        $this->title = $title;
        $this->description = $description;
        $this->categoryId = $categoryId;
        $this->uploadedById = $uploadedById;
        $this->s3Path = $s3Path;
    }

    public function validate(): bool
    {
        $this->validationErrors = [];

        // Validasi title
        if (!v::stringType()->length(1, 255)->validate($this->title)) {
            $this->validationErrors['title'] = 'Title must be a string between 1 and 255 characters.';
        }

        // Validasi description (jika ada)
        if ($this->description !== null && !v::stringType()->length(0, 1000)->validate($this->description)) {
            $this->validationErrors['description'] = 'Description must be a string up to 100 characters.';
        }

        // Validasi categoryId (jika ada)
        if ($this->categoryId !== null && !v::intType()->min(1)->validate($this->categoryId)) {
            $this->validationErrors['categoryId'] = 'Category ID must be a positive integer.';
        }

        // Validasi uploadedById
        if (!v::intType()->min(1)->validate($this->uploadedById)) {
            $this->validationErrors['uploadedById'] = 'Uploaded by ID must be a positive integer.';
        }

        // Validasi s3Path
        if (!v::stringType()->length(1, 1000)->validate($this->s3Path)) {
            $this->validationErrors['s3Path'] = 'S3 path must be a string between 1 and 1000 characters.';
        }

        return empty($this->validationErrors);
    }

    public function getValidationErrors(): array
    {
        return $this->validationErrors;
    }
}