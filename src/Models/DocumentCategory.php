<?php

namespace App\Models;

use DateTime;

class DocumentCategory
{
    public int $id;
    public string $name;
    public ?string $description;
    public string $createdAt;
    public string $updatedAt;

    public function __construct(
        int $id = null,
        string $name = '',
        string $description = null,
        string $createdAt = null,
        string $updatedAt = null
    ) {
        $this->id = $id ?? 0;
        $this->name = $name;
        $this->description = $description;
        $this->createdAt = $createdAt ?? (new DateTime())->format('Y-m-d H:i:s');
        $this->updatedAt = $updatedAt ?? (new DateTime())->format('Y-m-d H:i:s');
    }
}