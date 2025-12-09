<?php

require_once __DIR__ . '/../vendor/autoload.php';

use App\Config\Config;
use App\Services\DocumentService;
use App\Repositories\DocumentRepository;
use Aws\S3\S3Client;
use PDO;
use Predis\Client as Redis;

// Load environment variables
$dotenv = Dotenv\Dotenv::createImmutable(__DIR__ . '/../');
$dotenv->safeLoad();

// Initialize configuration
$configFiles = glob(__DIR__ . '/../config/*.php');
foreach ($configFiles as $configFile) {
    $configName = basename($configFile, '.php');
    $configData = include $configFile;
    Config::set($configName, $configData);
}

// Initialize services
$db = new PDO(
    sprintf(
        '%s:host=%s;port=%s;dbname=%s',
        $_ENV['DB_CONNECTION'] ?? 'pgsql',
        $_ENV['DB_HOST'] ?? 'localhost',
        $_ENV['DB_PORT'] ?? '5432',
        $_ENV['DB_DATABASE'] ?? 'document_ocr'
    ),
    $_ENV['DB_USERNAME'] ?? 'user',
    $_ENV['DB_PASSWORD'] ?? 'password',
    [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES => false,
    ]
);

$s3Client = new S3Client([
    'version' => 'latest',
    'region' => $_ENV['S3_REGION'] ?? 'us-east-1',
    'credentials' => [
        'key' => $_ENV['S3_KEY'] ?? '',
        'secret' => $_ENV['S3_SECRET'] ?? '',
    ],
    'endpoint' => $_ENV['S3_ENDPOINT'] ?? null,
    'use_path_style_endpoint' => filter_var($_ENV['S3_USE_PATH_STYLE_ENDPOINT'] ?? 'false', FILTER_VALIDATE_BOOLEAN),
]);

$documentRepository = new DocumentRepository($db);
$documentService = new DocumentService($documentRepository, $s3Client, $db);

// Connect to Redis for queue management
$redisConfig = [
    'scheme' => 'tcp',
    'host' => $_ENV['REDIS_HOST'] ?? 'localhost',
    'port' => (int)($_ENV['REDIS_PORT'] ?? 6379),
];

if (!empty($_ENV['REDIS_PASSWORD'])) {
    $redisConfig['password'] = $_ENV['REDIS_PASSWORD'];
}

if (isset($_ENV['REDIS_DB'])) {
    $redisConfig['database'] = (int)$_ENV['REDIS_DB'];
}

$redis = new Redis($redisConfig);

echo "OCR Worker initialized. Starting to process jobs...\n";

// Main worker loop
while (true) {
    try {
        // Get job from queue
        $jobData = $redis->brpop('ocr_processing_queue', 10); // 10 second timeout
        
        if ($jobData) {
            $job = json_decode($jobData[1], true);
            
            if ($job) {
                echo "Processing job: " . $job['id'] . " for document: " . $job['documentId'] . "\n";
                
                // Update document status to PROCESSING
                $documentService->updateDocumentStatus($job['documentId'], 'PROCESSING');
                
                // Download file from S3
                $s3Result = $s3Client->getObject([
                    'Bucket' => $_ENV['S3_BUCKET'],
                    'Key' => $job['s3Path']
                ]);
                
                $fileContent = $s3Result['Body'];
                
                // Perform OCR
                $ocrResult = performOCR($fileContent);
                
                // Extract document number
                $documentNumber = extractDocumentNumber($ocrResult['text']);
                
                // Update document with OCR results
                $documentService->updateDocumentWithOCRResults(
                    $job['documentId'], 
                    $ocrResult['text'], 
                    $documentNumber, 
                    $ocrResult['metadata']
                );
                
                // Index to Elasticsearch
                $documentService->indexToSearchEngine($job['documentId']);
                
                echo "Completed job: " . $job['id'] . " for document: " . $job['documentId'] . "\n";
            }
        }
        
        // Occasionally check for dead letter queue or perform maintenance
        if (rand(1, 100) === 1) {
            processDeadLetterQueue($redis, $documentService);
        }
        
    } catch (Exception $e) {
        echo "Error in worker: " . $e->getMessage() . "\n";
        error_log("OCR Worker Error: " . $e->getMessage());
        
        // Sleep briefly before continuing to avoid excessive error logging
        sleep(1);
    }
}

/**
 * Perform OCR on the document content
 */
function performOCR($fileContent): array
{
    $ocrEngine = $_ENV['OCR_ENGINE'] ?? 'tesseract';
    $ocrTimeout = (int)($_ENV['OCR_TIMEOUT'] ?? 300);
    $ocrLanguage = $_ENV['OCR_LANGUAGE'] ?? 'eng';
    
    // Save content to temporary file for OCR processing
    $tempFile = tempnam(sys_get_temp_dir(), 'ocr_');
    file_put_contents($tempFile, $fileContent);
    
    try {
        // Using tesseract via command line
        $command = sprintf(
            'tesseract %s stdout -l %s',
            escapeshellarg($tempFile),
            escapeshellarg($ocrLanguage)
        );
        
        $descriptorspec = [
            0 => ["pipe", "r"],  // stdin
            1 => ["pipe", "w"],  // stdout
            2 => ["pipe", "w"]   // stderr
        ];
        
        $process = proc_open($command, $descriptorspec, $pipes, null, null, ['timeout' => $ocrTimeout]);
        
        if (is_resource($process)) {
            $output = stream_get_contents($pipes[1]);
            $error = stream_get_contents($pipes[2]);
            
            fclose($pipes[1]);
            fclose($pipes[2]);
            
            $return_value = proc_close($process);
            
            if ($return_value !== 0) {
                throw new Exception("OCR failed with error: {$error}");
            }
            
            // Calculate confidence based on output quality
            $confidence = calculateOCRScore($output);
            
            return [
                'text' => $output,
                'metadata' => [
                    'engine' => $ocrEngine,
                    'language' => $ocrLanguage,
                    'confidence' => $confidence,
                    'timestamp' => date('c'),
                    'engine_version' => getOCRVersion($ocrEngine)
                ]
            ];
        } else {
            throw new Exception("Failed to start OCR process");
        }
    } finally {
        // Clean up temp file
        if (file_exists($tempFile)) {
            unlink($tempFile);
        }
    }
}

/**
 * Extract document number from OCR text using regex patterns
 */
function extractDocumentNumber(string $ocrText): ?string
{
    $patterns = [
        'invoice' => '/\b(?:INV|INVOICE)[-\s]?([A-Z0-9]{4,12})\b/i',
        'po' => '/\b(?:PO|PURCHASE ORDER)[-\s]?([A-Z0-9]{4,12})\b/i',
        'dn' => '/\b(?:DN|DELIVERY NOTE)[-\s]?([A-Z0-9]{4,12})\b/i',
        'general_number' => '/\b(?:NO|NOMOR|NUMBER)[-\s]?([A-Z0-9]{4,12})\b/i',
    ];
    
    // Add custom pattern if defined
    if (!empty($_ENV['CUSTOM_DOC_NUMBER_PATTERN'])) {
        $patterns['custom'] = $_ENV['CUSTOM_DOC_NUMBER_PATTERN'];
    }
    
    foreach ($patterns as $pattern) {
        if (preg_match($pattern, $ocrText, $matches)) {
            return trim($matches[1]);
        }
    }
    
    // If no specific pattern matched, try to find any likely document number format
    if (preg_match('/\b[A-Z]{2,4}[ -_]?\d{3,8}\b|\b\d{4,8}\b/', $ocrText, $matches)) {
        return trim($matches[0]);
    }
    
    return null;
}

/**
 * Calculate OCR confidence score based on output quality
 */
function calculateOCRScore(string $text): float
{
    // Simple heuristic for confidence calculation
    // In a real implementation, this would be more sophisticated
    $length = strlen($text);
    
    if ($length === 0) {
        return 0.0;
    }
    
    // Count non-letter/number characters as potential noise
    $cleanText = preg_replace('/[^a-zA-Z0-9\s]/', ' ', $text);
    $noiseRatio = ($length - strlen($cleanText)) / $length;
    
    // Length-based confidence (longer text is generally more reliable)
    $lengthScore = min($length / 100, 1.0); // Cap at 1.0 for texts over 100 chars
    
    // Return combined score
    return max(0.1, (1 - $noiseRatio) * $lengthScore);
}

/**
 * Get OCR engine version
 */
function getOCRVersion(string $engine): string
{
    switch ($engine) {
        case 'tesseract':
            $versionOutput = shell_exec('tesseract --version');
            if ($versionOutput && preg_match('/tesseract ([0-9.]+)/', $versionOutput, $matches)) {
                return $matches[1];
            }
            return 'unknown';
        default:
            return 'unknown';
    }
}

/**
 * Process dead letter queue for failed jobs
 */
function processDeadLetterQueue($redis, $documentService): void
{
    // Check for any failed jobs in the dead letter queue
    $failedJobs = $redis->lrange('ocr_failed_jobs', 0, -1);
    
    foreach ($failedJobs as $failedJob) {
        $job = json_decode($failedJob, true);
        
        // Log the failed job for manual inspection
        error_log("Failed OCR job: " . print_r($job, true));
        
        // Update document status to FAILED
        $documentService->updateDocumentStatus($job['documentId'], 'FAILED', $job['error'] ?? 'Unknown error');
        
        // Remove from dead letter queue
        $redis->lrem('ocr_failed_jobs', 1, $failedJob);
    }
}