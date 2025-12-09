<?php

namespace App\Repositories;

use App\Models\User;
use PDO;
use PDOException;

class UserRepository
{
    private PDO $pdo;

    public function __construct(PDO $pdo)
    {
        $this->pdo = $pdo;
    }

    public function findById(string $id): ?User
    {
        try {
            $stmt = $this->pdo->prepare("SELECT * FROM users WHERE id = :id");
            $stmt->bindParam(':id', $id);
            $stmt->execute();
            
            $data = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if (!$data) {
                return null;
            }
            
            return $this->mapDataToUser($data);
        } catch (PDOException $e) {
            error_log("Database error in UserRepository::findById: " . $e->getMessage());
            return null;
        }
    }

    public function findByEmail(string $email): ?User
    {
        try {
            $stmt = $this->pdo->prepare("SELECT * FROM users WHERE email = :email");
            $stmt->bindParam(':email', $email);
            $stmt->execute();
            
            $data = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if (!$data) {
                return null;
            }
            
            return $this->mapDataToUser($data);
        } catch (PDOException $e) {
            error_log("Database error in UserRepository::findByEmail: " . $e->getMessage());
            return null;
        }
    }

    public function create(User $user): ?User
    {
        try {
            $stmt = $this->pdo->prepare("
                INSERT INTO users (id, email, password_hash, role, permissions, is_active, last_login_at, created_at, updated_at) 
                VALUES (:id, :email, :password_hash, :role, :permissions, :is_active, :last_login_at, :created_at, :updated_at)
            ");
            
            $permissionsJson = json_encode($user->permissions);
            
            $stmt->bindParam(':id', $user->id);
            $stmt->bindParam(':email', $user->email);
            $stmt->bindParam(':password_hash', $user->passwordHash);
            $stmt->bindParam(':role', $user->role);
            $stmt->bindParam(':permissions', $permissionsJson);
            $stmt->bindParam(':is_active', $user->isActive, PDO::PARAM_BOOL);
            $stmt->bindParam(':last_login_at', $user->lastLoginAt);
            $stmt->bindParam(':created_at', $user->createdAt);
            $stmt->bindParam(':updated_at', $user->updatedAt);
            
            $result = $stmt->execute();
            
            return $result ? $user : null;
        } catch (PDOException $e) {
            error_log("Database error in UserRepository::create: " . $e->getMessage());
            return null;
        }
    }

    public function update(User $user): ?User
    {
        try {
            $stmt = $this->pdo->prepare("
                UPDATE users 
                SET email = :email, 
                    password_hash = :password_hash, 
                    role = :role, 
                    permissions = :permissions, 
                    is_active = :is_active, 
                    last_login_at = :last_login_at, 
                    updated_at = :updated_at 
                WHERE id = :id
            ");
            
            $permissionsJson = json_encode($user->permissions);
            
            $stmt->bindParam(':id', $user->id);
            $stmt->bindParam(':email', $user->email);
            $stmt->bindParam(':password_hash', $user->passwordHash);
            $stmt->bindParam(':role', $user->role);
            $stmt->bindParam(':permissions', $permissionsJson);
            $stmt->bindParam(':is_active', $user->isActive, PDO::PARAM_BOOL);
            $stmt->bindParam(':last_login_at', $user->lastLoginAt);
            $stmt->bindParam(':updated_at', $user->updatedAt);
            
            $result = $stmt->execute();
            
            return $result ? $user : null;
        } catch (PDOException $e) {
            error_log("Database error in UserRepository::update: " . $e->getMessage());
            return null;
        }
    }

    public function delete(string $id): bool
    {
        try {
            $stmt = $this->pdo->prepare("DELETE FROM users WHERE id = :id");
            $stmt->bindParam(':id', $id);
            
            return $stmt->execute();
        } catch (PDOException $e) {
            error_log("Database error in UserRepository::delete: " . $e->getMessage());
            return false;
        }
    }

    private function mapDataToUser(array $data): User
    {
        $user = new User();
        $user->id = $data['id'];
        $user->email = $data['email'];
        $user->passwordHash = $data['password_hash'];
        $user->role = $data['role'];
        $user->permissions = json_decode($data['permissions'] ?? '[]', true) ?: [];
        $user->isActive = (bool)$data['is_active'];
        $user->lastLoginAt = $data['last_login_at'];
        $user->createdAt = $data['created_at'];
        $user->updatedAt = $data['updated_at'];
        
        return $user;
    }
}