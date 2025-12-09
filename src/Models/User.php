<?php

namespace App\Models;

use Ramsey\Uuid\Uuid;
use DateTime;

class User
{
    public string $id;
    public string $email;
    public string $passwordHash;
    public string $role;
    public array $permissions;
    public bool $isActive;
    public ?string $lastLoginAt;
    public string $createdAt;
    public string $updatedAt;

    public function __construct(
        string $id = null,
        string $email = '',
        string $passwordHash = '',
        string $role = 'user',
        array $permissions = [],
        bool $isActive = true,
        string $lastLoginAt = null,
        string $createdAt = null,
        string $updatedAt = null
    ) {
        $this->id = $id ?? Uuid::uuid4()->toString();
        $this->email = $email;
        $this->passwordHash = $passwordHash;
        $this->role = $role;
        $this->permissions = $permissions;
        $this->isActive = $isActive;
        $this->lastLoginAt = $lastLoginAt;
        $this->createdAt = $createdAt ?? (new DateTime())->format('Y-m-d H:i:s');
        $this->updatedAt = $updatedAt ?? (new DateTime())->format('Y-m-d H:i:s');
    }

    public function hasPermission(string $permission): bool
    {
        return in_array($permission, $this->permissions) || 
               in_array('admin', $this->permissions);
    }

    public function hasRole(string $role): bool
    {
        return $this->role === $role || $this->role === 'admin';
    }
}