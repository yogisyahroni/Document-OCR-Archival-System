<?php

namespace App\Exceptions;

use Exception;

class ValidationException extends Exception
{
    private array $errors;

    public function __construct(string $message = "", array $errors = [])
    {
        parent::__construct($message);
        $this->errors = $errors;
    }

    public function getErrors(): array
    {
        return $this->errors;
    }

    public function setError(string $field, string $error): void
    {
        $this->errors[$field] = $error;
    }

    public function hasErrors(): bool
    {
        return !empty($this->errors);
    }

    public function getErrorMessage(): string
    {
        if (empty($this->errors)) {
            return $this->getMessage();
        }

        $errorMessages = [];
        foreach ($this->errors as $field => $error) {
            $errorMessages[] = "{$field}: {$error}";
        }

        return implode(', ', $errorMessages);
    }
}