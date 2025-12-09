<?php

use App\Config\Config;

// Define the application directory
define('APP_PATH', dirname(__DIR__));
define('PUBLIC_PATH', __DIR__);

// Load Composer autoloader
require_once APP_PATH . '/vendor/autoload.php';

// Load environment variables
$dotenv = Dotenv\Dotenv::createImmutable(APP_PATH);
$dotenv->load();

// Initialize configuration
Config::get('app.name');

// Create the application container
$container = require_once APP_PATH . '/bootstrap/app.php';

// Define response function for API responses
function sendJsonResponse($data, $statusCode = 200) {
    http_response_code($statusCode);
    header('Content-Type: application/json');
    echo json_encode($data);
    exit;
}

// Define error handler for API responses
function handleError($errno, $errstr, $errfile, $errline) {
    if (!(error_reporting() & $errno)) {
        return false;
    }
    
    $error = [
        'success' => false,
        'error' => [
            'message' => $errstr,
            'file' => $errfile,
            'line' => $errline,
            'code' => $errno
        ]
    ];
    
    sendJsonResponse($error, 500);
}

set_error_handler('handleError');

// Get the requested URI and method
$requestUri = $_SERVER['REQUEST_URI'] ?? '/';
$httpMethod = $_SERVER['REQUEST_METHOD'] ?? 'GET';

// Parse the request URI to extract the path and query
$parsedUrl = parse_url($requestUri);
$path = $parsedUrl['path'] ?? '';
$queryString = $parsedUrl['query'] ?? '';

// Route handling
$routes = [
    'GET /api/health' => function () {
        sendJsonResponse([
            'success' => true,
            'data' => [
                'status' => 'healthy',
                'timestamp' => date('c'),
                'version' => '1.0.0'
            ]
        ]);
    },
    
    'GET /api/documents' => function () use ($container) {
        $documentService = $container->get(App\Services\DocumentService::class);
        $page = (int)($_GET['page'] ?? 1);
        $limit = min((int)($_GET['limit'] ?? 10), 100); // Max 100 per page
        $filters = [
            'status' => $_GET['status'] ?? null,
            'category_id' => (int)($_GET['category_id'] ?? 0) ?: null,
            'uploaded_by_id' => (int)($_GET['uploaded_by_id'] ?? 0) ?: null
        ];
        
        $result = $documentService->findAll($page, $limit, $filters);
        sendJsonResponse([
            'success' => true,
            'data' => $result
        ]);
    },
    
    'POST /api/documents' => function () use ($container) {
        // Check authentication
        $authHeader = $_SERVER['HTTP_AUTHORIZATION'] ?? '';
        if (empty($authHeader) || !preg_match('/Bearer\s+(.*)$/i', $authHeader, $matches)) {
            sendJsonResponse([
                'success' => false,
                'error' => 'Unauthorized: Missing or invalid token'
            ], 401);
        }
        
        $token = $matches[1];
        $authService = $container->get(App\Services\AuthService::class);
        $user = $authService->getUserFromToken($token);
        
        if (!$user) {
            sendJsonResponse([
                'success' => false,
                'error' => 'Unauthorized: Invalid token'
            ], 401);
        }
        
        // Check if user has permission to upload documents
        if (!$user->hasPermission('documents.create')) {
            sendJsonResponse([
                'success' => false,
                'error' => 'Forbidden: Insufficient permissions'
            ], 403);
        }
        
        // Process file upload
        if (!isset($_FILES['file'])) {
            sendJsonResponse([
                'success' => false,
                'error' => 'Missing file in request'
            ], 400);
        }
        
        $file = $_FILES['file'];
        if ($file['error'] !== UPLOAD_ERR_OK) {
            sendJsonResponse([
                'success' => false,
                'error' => 'File upload error: ' . $file['error']
            ], 400);
        }
        
        // Validate file type
        $allowedTypes = ['application/pdf', 'image/jpeg', 'image/png', 'image/tiff'];
        if (!in_array($file['type'], $allowedTypes)) {
            sendJsonResponse([
                'success' => false,
                'error' => 'Invalid file type. Allowed types: PDF, JPEG, PNG, TIFF'
            ], 400);
        }
        
        // Validate file size (max 10MB)
        if ($file['size'] > 10 * 1024 * 1024) {
            sendJsonResponse([
                'success' => false,
                'error' => 'File too large. Maximum size is 10MB'
            ], 400);
        }
        
        // Process the upload
        $documentService = $container->get(App\Services\DocumentService::class);
        $s3Path = $documentService->uploadFile($file);
        
        if (!$s3Path) {
            sendJsonResponse([
                'success' => false,
                'error' => 'Failed to upload file to storage'
            ], 500);
        }
        
        // Create document record
        $title = $_POST['title'] ?? basename($file['name'], '.' . pathinfo($file['name'], PATHINFO_EXTENSION));
        $description = $_POST['description'] ?? null;
        $categoryId = (int)($_POST['category_id'] ?? 0) ?: null;
        
        $dto = new App\DTOs\CreateDocumentDTO(
            $title,
            $description,
            $categoryId,
            $user->id,
            $s3Path
        );
        
        $document = $documentService->create($dto);
        
        if (!$document) {
            sendJsonResponse([
                'success' => false,
                'error' => 'Failed to create document record'
            ], 500);
        }
        
        sendJsonResponse([
            'success' => true,
            'data' => [
                'id' => $document->id,
                'title' => $document->title,
                'status' => $document->status,
                'created_at' => $document->createdAt
            ]
        ], 202); // Return 202 Accepted as processing will happen asynchronously
    },
    
    'GET /api/documents/{id}' => function ($id) use ($container) {
        $documentService = $container->get(App\Services\DocumentService::class);
        $document = $documentService->findById($id);
        
        if (!$document) {
            sendJsonResponse([
                'success' => false,
                'error' => 'Document not found'
            ], 404);
        }
        
        // Check if user has permission to view this document
        $authHeader = $_SERVER['HTTP_AUTHORIZATION'] ?? '';
        if (!empty($authHeader) && preg_match('/Bearer\s+(.*)$/i', $authHeader, $matches)) {
            $token = $matches[1];
            $authService = $container->get(App\Services\AuthService::class);
            $user = $authService->getUserFromToken($token);
            
            if ($user && $user->id !== $document->uploadedById) {
                // Additional permission check might be needed here
                // For now, allow access if user is authenticated
            }
        }
        
        sendJsonResponse([
            'success' => true,
            'data' => $document
        ]);
    },
    
    'GET /api/search' => function () use ($container) {
        $query = $_GET['q'] ?? '';
        $page = (int)($_GET['page'] ?? 1);
        $limit = min((int)($_GET['limit'] ?? 10), 100); // Max 100 per page
        
        if (empty($query)) {
            sendJsonResponse([
                'success' => false,
                'error' => 'Search query is required'
            ], 400);
        }
        
        // For now, use document service to search
        // In a real implementation, this would use Elasticsearch
        $documentService = $container->get(App\Services\DocumentService::class);
        
        // Get user ID from token for permission checking
        $userId = 0; // Default to 0 if not authenticated
        $authHeader = $_SERVER['HTTP_AUTHORIZATION'] ?? '';
        if (!empty($authHeader) && preg_match('/Bearer\s+(.*)$/i', $authHeader, $matches)) {
            $token = $matches[1];
            $authService = $container->get(App\Services\AuthService::class);
            $user = $authService->getUserFromToken($token);
            
            if ($user) {
                $userId = $user->id;
            }
        }
        
        $results = $documentService->searchDocuments($query, $userId, $page, $limit);
        
        sendJsonResponse([
            'success' => true,
            'data' => $results
        ]);
    }
];

// Find matching route
$routeKey = $httpMethod . ' ' . $path;
$routeFound = false;

foreach ($routes as $pattern => $handler) {
    if ($pattern === $routeKey) {
        $handler();
        $routeFound = true;
        break;
    }
    
    // Check for wildcard routes (like GET /api/documents/{id})
    if (strpos($pattern, '{') !== false) {
        $patternRegex = preg_quote($pattern, '/');
        $patternRegex = str_replace('\{id\}', '([^/]+)', $patternRegex);
        $patternRegex = '/^' . $patternRegex . '$/';
        
        if (preg_match($patternRegex, $path, $matches)) {
            // Remove full match and pass captured groups as parameters
            array_shift($matches);
            $handler(...$matches);
            $routeFound = true;
            break;
        }
    }
}

// If no route matches, return 404
if (!$routeFound) {
    sendJsonResponse([
        'success' => false,
        'error' => 'Route not found: ' . $path
    ], 404);
}