<?php

namespace App\Services;

use App\Models\User;
use App\Repositories\UserRepository;
use Firebase\JWT\JWT;
use Firebase\JWT\Key;
use DateTime;
use Exception;

class AuthService
{
    private UserRepository $userRepository;
    private string $jwtSecret;
    private string $jwtRefreshSecret;
    private int $jwtTtl; // in minutes
    private int $jwtRefreshTtl; // in minutes

    public function __construct(
        UserRepository $userRepository,
        string $jwtSecret = null,
        string $jwtRefreshSecret = null,
        int $jwtTtl = null,
        int $jwtRefreshTtl = null
    ) {
        $this->userRepository = $userRepository;
        $this->jwtSecret = $jwtSecret ?? $_ENV['JWT_SECRET'] ?? throw new Exception('JWT_SECRET not configured');
        $this->jwtRefreshSecret = $jwtRefreshSecret ?? $_ENV['JWT_REFRESH_SECRET'] ?? throw new Exception('JWT_REFRESH_SECRET not configured');
        $this->jwtTtl = $jwtTtl ?? (int)($_ENV['JWT_TTL'] ?? 15); // 15 minutes default
        $this->jwtRefreshTtl = $jwtRefreshTtl ?? (int)($_ENV['JWT_REFRESH_TTL'] ?? 10080); // 7 days default
    }

    public function login(string $email, string $password): ?array
    {
        $user = $this->userRepository->findByEmail($email);
        
        if (!$user || !password_verify($password, $user->passwordHash)) {
            return null;
        }

        // Update last login
        $user->lastLoginAt = (new DateTime())->format('Y-m-d H:i:s');
        $this->userRepository->update($user);

        return [
            'user' => $user,
            'token' => $this->generateAccessToken($user),
            'refresh_token' => $this->generateRefreshToken($user)
        ];
    }

    public function register(string $email, string $password, string $name = ''): ?User
    {
        // Check if user already exists
        if ($this->userRepository->findByEmail($email)) {
            return null;
        }

        // Hash password
        $passwordHash = password_hash($password, PASSWORD_ARGON2ID);

        // Create new user
        $user = new User(
            null,
            $email,
            $passwordHash,
            'user', // default role
            [], // default permissions
            true,
            null,
            (new DateTime())->format('Y-m-d H:i:s'),
            (new DateTime())->format('Y-m-d H:i:s')
        );

        return $this->userRepository->create($user);
    }

    public function generateAccessToken(User $user): string
    {
        $payload = [
            'iss' => 'document-ocr-system',
            'sub' => $user->id,
            'email' => $user->email,
            'role' => $user->role,
            'permissions' => $user->permissions,
            'iat' => time(),
            'exp' => time() + ($this->jwtTtl * 60) // Convert minutes to seconds
        ];

        return JWT::encode($payload, $this->jwtSecret, 'HS256');
    }

    public function generateRefreshToken(User $user): string
    {
        $payload = [
            'sub' => $user->id,
            'type' => 'refresh',
            'iat' => time(),
            'exp' => time() + ($this->jwtRefreshTtl * 60) // Convert minutes to seconds
        ];

        return JWT::encode($payload, $this->jwtRefreshSecret, 'HS256');
    }

    public function validateAccessToken(string $token): ?array
    {
        try {
            $decoded = JWT::decode($token, new Key($this->jwtSecret, 'HS256'));
            
            // Check if token is expired
            if (isset($decoded->exp) && $decoded->exp < time()) {
                return null;
            }

            return (array) $decoded;
        } catch (Exception $e) {
            return null;
        }
    }

    public function validateRefreshToken(string $token): ?array
    {
        try {
            $decoded = JWT::decode($token, new Key($this->jwtRefreshSecret, 'HS256'));
            
            // Check if token is expired
            if (isset($decoded->exp) && $decoded->exp < time()) {
                return null;
            }

            return (array) $decoded;
        } catch (Exception $e) {
            return null;
        }
    }

    public function refreshAccessToken(string $refreshToken): ?array
    {
        $payload = $this->validateRefreshToken($refreshToken);
        
        if (!$payload || !isset($payload['sub'])) {
            return null;
        }

        $user = $this->userRepository->findById($payload['sub']);
        
        if (!$user) {
            return null;
        }

        return [
            'token' => $this->generateAccessToken($user),
            'refresh_token' => $refreshToken // Return the same refresh token for now
        ];
    }

    public function getUserFromToken(string $token): ?User
    {
        $payload = $this->validateAccessToken($token);
        
        if (!$payload || !isset($payload['sub'])) {
            return null;
        }

        return $this->userRepository->findById($payload['sub']);
    }
}