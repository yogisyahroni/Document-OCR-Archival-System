<?php

use App\Config\Config;
use App\Utils\SimpleContainer;
use App\Utils\Router;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Message\ResponseInterface;
use GuzzleHttp\Psr7\Response;
use GuzzleHttp\Psr7\ServerRequest;

// Autoload dependencies
require_once __DIR__ . '/vendor/autoload.php';

// Initialize environment
$dotenv = Dotenv\Dotenv::createImmutable(__DIR__);
$dotenv->safeLoad();

// Initialize the configuration
$configFiles = glob(__DIR__ . '/config/*.php');
foreach ($configFiles as $configFile) {
    $configName = basename($configFile, '.php');
    $configData = include $configFile;
    Config::set($configName, $configData);
}

// Create the application container
$app = new SimpleContainer();

// Register core services
$app->bind('config', function () {
    return Config::get('app');
});

// Database connection
$app->singleton(PDO::class, function () {
    $config = Config::get('database.connections.pgsql');
    
    $dsn = sprintf(
        '%s:host=%s;port=%s;dbname=%s',
        $config['driver'],
        $config['host'],
        $config['port'],
        $config['database']
    );
    
    $options = [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES => false,
    ];
    
    return new PDO($dsn, $config['username'], $config['password'], $options);
});

// S3 Client
$app->singleton(Aws\S3\S3Client::class, function () {
    $config = Config::get('database.document_storage.s3');
    
    return new Aws\S3\S3Client([
        'version' => 'latest',
        'region' => $config['region'],
        'credentials' => [
            'key' => $config['key'],
            'secret' => $config['secret'],
        ],
        'endpoint' => $config['endpoint'] ?? null,
        'use_path_style_endpoint' => $config['use_path_style_endpoint'] ?? false,
    ]);
});

// Register repositories
$app->singleton(App\Repositories\UserRepository::class, function ($container) {
    return new App\Repositories\UserRepository($container->get(PDO::class));
});

$app->singleton(App\Repositories\DocumentRepository::class, function ($container) {
    return new App\Repositories\DocumentRepository($container->get(PDO::class));
});

// Register services
$app->singleton(App\Services\AuthService::class, function ($container) {
    return new App\Services\AuthService(
        $container->get(App\Repositories\UserRepository::class),
        Config::get('jwt.secret'),
        Config::get('jwt.refresh_secret'),
        Config::get('jwt.ttl'),
        Config::get('jwt.refresh_ttl')
    );
});

$app->singleton(App\Services\DocumentService::class, function ($container) {
    return new App\Services\DocumentService(
        $container->get(App\Repositories\DocumentRepository::class),
        $container->get(Aws\S3\S3Client::class),
        $container->get(PDO::class)
    );
});

// Initialize router
$router = new Router();

// Authentication middleware function
function authenticateRequest(ServerRequestInterface $request, SimpleContainer $container): array
{
    $authHeader = $request->getHeaderLine('Authorization');
    
    if (empty($authHeader) || !preg_match('/^Bearer\s+(.*)$/i', $authHeader, $matches)) {
        return [
            'authenticated' => false,
            'response' => new Response(
                401,
                ['Content-Type' => 'application/json'],
                json_encode(['error' => 'Unauthorized: Missing or invalid token'])
            )
        ];
    }
    
    $token = $matches[1];
    $authService = $container->get(App\Services\AuthService::class);
    
    try {
        $payload = $authService->validateAccessToken($token);
        if (!$payload) {
            return [
                'authenticated' => false,
                'response' => new Response(
                    401,
                    ['Content-Type' => 'application/json'],
                    json_encode(['error' => 'Unauthorized: Invalid token'])
                )
            ];
        }
        
        return [
            'authenticated' => true,
            'user_id' => $payload['sub']
        ];
    } catch (Exception $e) {
        return [
            'authenticated' => false,
            'response' => new Response(
                401,
                ['Content-Type' => 'application/json'],
                json_encode(['error' => 'Unauthorized: Token validation failed'])
            )
        ];
    }
}

// API Routes
$router->get('/api/health', function (ServerRequestInterface $request, SimpleContainer $container) {
    return new Response(
        200,
        ['Content-Type' => 'application/json'],
        json_encode([
            'status' => 'healthy',
            'timestamp' => date('c'),
            'version' => '1.0.0'
        ])
    );
});

$router->post('/api/auth/login', function (ServerRequestInterface $request, SimpleContainer $container) {
    $authService = $container->get(App\Services\AuthService::class);
    
    $body = json_decode($request->getBody()->getContents(), true);
    
    if (!isset($body['email']) || !isset($body['password'])) {
        return new Response(
            400,
            ['Content-Type' => 'application/json'],
            json_encode(['error' => 'Email and password are required'])
        );
    }
    
    $result = $authService->login($body['email'], $body['password']);
    
    if (!$result) {
        return new Response(
            401,
            ['Content-Type' => 'application/json'],
            json_encode(['error' => 'Invalid credentials'])
        );
    }
    
    return new Response(
        200,
        ['Content-Type' => 'application/json'],
        json_encode([
            'success' => true,
            'data' => [
                'user' => $result['user'],
                'token' => $result['token'],
                'refresh_token' => $result['refresh_token']
            ]
        ])
    );
});

$router->post('/api/documents', function (ServerRequestInterface $request, SimpleContainer $container) {
    // Check authentication
    $authResult = authenticateRequest($request, $container);
    if (!$authResult['authenticated']) {
        return $authResult['response'];
    }
    
    $documentService = $container->get(App\Services\DocumentService::class);
    
    // Get uploaded file
    $uploadedFiles = $request->getUploadedFiles();
    if (!isset($uploadedFiles['file'])) {
        return new Response(
            400,
            ['Content-Type' => 'application/json'],
            json_encode(['error' => 'File is required'])
        );
    }
    
    $file = $uploadedFiles['file'];
    $body = json_decode($request->getBody()->getContents(), true);
    
    // Validate file
    $allowedTypes = ['application/pdf', 'image/jpeg', 'image/png', 'image/tiff'];
    if (!in_array($file->getClientMediaType(), $allowedTypes)) {
        return new Response(
            400,
            ['Content-Type' => 'application/json'],
            json_encode(['error' => 'Invalid file type'])
        );
    }
    
    // Validate file size (max 10MB)
    if ($file->getSize() > 10 * 1024 * 1024) {
        return new Response(
            400,
            ['Content-Type' => 'application/json'],
            json_encode(['error' => 'File too large'])
        );
    }
    
    // Get user ID from authentication result
    $userId = $authResult['user_id'];
    
    // Create document DTO
    $dto = new \App\DTOs\CreateDocumentDTO(
        $userId,
        '', // Will be set after upload
        $body['title'] ?? 'Untitled Document',
        $body['description'] ?? null,
        isset($body['category_id']) ? (int)$body['category_id'] : null
    );
    
    // Validate DTO
    if (!$dto->validate()) {
        return new Response(
            400,
            ['Content-Type' => 'application/json'],
            json_encode(['error' => 'Validation failed', 'details' => $dto->getValidationErrors()])
        );
    }
    
    // Upload file to S3 and create document
    $result = $documentService->create($dto, $file);
    
    if (!$result) {
        return new Response(
            500,
            ['Content-Type' => 'application/json'],
            json_encode(['error' => 'Failed to create document'])
        );
    }
    
    return new Response(
        202,
        ['Content-Type' => 'application/json'],
        json_encode([
            'success' => true,
            'data' => [
                'id' => $result->id,
                'title' => $result->title,
                'status' => $result->status,
                'created_at' => $result->createdAt
            ],
            'message' => 'Document uploaded successfully. Processing in background.'
        ])
    );
});

$router->get('/api/documents/{id}', function (ServerRequestInterface $request, SimpleContainer $container) {
    // Check authentication
    $authResult = authenticateRequest($request, $container);
    if (!$authResult['authenticated']) {
        return $authResult['response'];
    }
    
    $documentService = $container->get(App\Services\DocumentService::class);
    
    // Extract document ID from path
    $path = $request->getUri()->getPath();
    $pathParts = explode('/', $path);
    $documentId = end($pathParts);
    
    $document = $documentService->findById($documentId);
    
    if (!$document) {
        return new Response(
            404,
            ['Content-Type' => 'application/json'],
            json_encode(['error' => 'Document not found'])
        );
    }
    
    return new Response(
        200,
        ['Content-Type' => 'application/json'],
        json_encode([
            'success' => true,
            'data' => $document
        ])
    );
});

$router->get('/api/search', function (ServerRequestInterface $request, SimpleContainer $container) {
    // Check authentication
    $authResult = authenticateRequest($request, $container);
    if (!$authResult['authenticated']) {
        return $authResult['response'];
    }
    
    $documentService = $container->get(App\Services\DocumentService::class);
    
    $queryParams = $request->getQueryParams();
    $query = $queryParams['q'] ?? '';
    $page = (int)($queryParams['page'] ?? 1);
    $limit = min((int)($queryParams['limit'] ?? 10), 100);
    
    if (empty($query)) {
        return new Response(
            400,
            ['Content-Type' => 'application/json'],
            json_encode(['error' => 'Search query is required'])
        );
    }
    
    $userId = $authResult['user_id'];
    $results = $documentService->searchDocuments($query, $userId, $page, $limit);
    
    return new Response(
        200,
        ['Content-Type' => 'application/json'],
        json_encode([
            'success' => true,
            'data' => $results
        ])
    );
});

// Get the request
$request = ServerRequest::fromGlobals();

// Dispatch request to appropriate handler
$response = $router->dispatch($request, $app);

// Send response
http_response_code($response->getStatusCode());

foreach ($response->getHeaders() as $name => $values) {
    foreach ($values as $value) {
        header(sprintf('%s: %s', $name, $value), false);
    }
}

echo $response->getBody();