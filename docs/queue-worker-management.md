# Queue & Worker Management - Document OCR & Archival System

## Ikhtisar
Dokumen ini menjelaskan pengelolaan queue dan worker dalam sistem Document OCR & Archival System, yang merupakan komponen kritis untuk pemrosesan dokumen secara asinkron.

## Arsitektur Queue

### Komponen Utama
- **Queue Broker**: Redis sebagai penyimpanan pesan dan manajemen antrian
- **Producer**: API endpoint yang menempatkan job ke queue
- **Consumer**: PHP worker yang memproses job dari queue
- **Dead Letter Queue**: Untuk job yang gagal diproses

### Alur Pemrosesan
```
┌─────────────────┐    ┌─────────────────┐    ┌─────────────────┐
│   API Request   │ -> │  Queue (Redis)  │ -> │   Worker(s)     │
│ (Upload Doc)    │    │                 │    │ (OCR Process)   │
└─────────────────┘    └─────────────────┘    └─────────────────┘
         │                       │                       │
         ▼                       ▼
┌─────────────────┐    ┌─────────────────┐    ┌─────────────────┐
│ Create Job      │ -> │ Job Persistence │ -> │ Process & Update│
│ DocumentIngest  │    │ in Redis        │    │ DB/Elasticsearch│
└─────────────────┘    └─────────────────┘    └─────────────────┘
```

## Jenis Queue dan Job

### 1. DocumentIngestQueue
- **Deskripsi**: Queue utama untuk pemrosesan dokumen baru
- **Job**: DocumentIngestJob
- **Prioritas**: Tinggi
- **Timeout**: 30 menit
- **Retry**: 3 kali dengan exponential backoff

### 2. OCRProcessingQueue
- **Deskripsi**: Queue untuk eksekusi OCR dan ekstraksi teks
- **Job**: OCRProcessingJob
- **Prioritas**: Tinggi
- **Timeout**: 60 menit (tergantung ukuran dokumen)
- **Retry**: 2 kali

### 3. IndexingQueue
- **Deskripsi**: Queue untuk pengindeksan ke Elasticsearch
- **Job**: IndexingJob
- **Prioritas**: Sedang
- **Timeout**: 10 menit
- **Retry**: 3 kali

## Konfigurasi Queue

### File Konfigurasi
```php
// config/queue.php
return [
    'default' => 'redis',
    
    'connections' => [
        'redis' => [
            'driver' => 'redis',
            'connection' => 'default',
            'queue' => env('REDIS_QUEUE', 'default'),
            'retry_after' => 90,
            'block_for' => null,
        ],
    ],
    
    'failed' => [
        'driver' => 'database-uuids',
        'database' => 'pgsql',
        'table' => 'failed_jobs',
    ],
];
```

### Environment Variables
```env
# Queue Configuration
QUEUE_CONNECTION=redis
REDIS_QUEUE=default

# Worker Configuration
WORKER_MAX_JOBS=1000
WORKER_MEMORY_LIMIT=512
WORKER_TIMEOUT=3600
WORKER_SLEEP=3
WORKER_MAX_TRIES=3
```

## Implementasi Worker

### Struktur Worker
```php
// workers/ocr-worker.php
<?php

require_once __DIR__ . '/../src/Init.php';
App\Init::initialize();

use App\Services\DocumentService;
use App\Models\Document;
use App\Models\DocumentOCRJob;

class OCRWorker
{
    private DocumentService $documentService;
    private int $maxJobs;
    private int $memoryLimit;
    
    public function __construct()
    {
        $this->documentService = new DocumentService();
        $this->maxJobs = (int)($_ENV['WORKER_MAX_JOBS'] ?? 1000);
        $this->memoryLimit = (int)($_ENV['WORKER_MEMORY_LIMIT'] ?? 512) * 1024 * 1024; // bytes
    }
    
    public function run(): void
    {
        $processedJobs = 0;
        
        while (true) {
            // Cek limit memory
            if (memory_get_usage() > $this->memoryLimit) {
                echo "Memory limit reached. Exiting worker.\n";
                break;
            }
            
            // Cek jumlah job yang telah diproses
            if ($processedJobs >= $this->maxJobs) {
                echo "Max jobs limit reached. Exiting worker.\n";
                break;
            }
            
            // Ambil job dari queue
            $job = $this->getNextJob();
            
            if ($job) {
                $this->processJob($job);
                $processedJobs++;
            } else {
                // Tidur sejenak jika tidak ada job
                sleep((int)($_ENV['WORKER_SLEEP'] ?? 3));
            }
            
            // Cek sinyal untuk graceful shutdown
            if ($this->shouldShutdown()) {
                break;
            }
        }
    }
    
    private function getNextJob()
    {
        // Implementasi untuk mendapatkan job dari Redis
        // Menggunakan BLPOP atau metode lain tergantung implementasi queue
        return DocumentOCRJob::getNextPending();
    }
    
    private function processJob($job): void
    {
        try {
            $document = Document::find($job->document_id);
            if (!$document) {
                throw new Exception("Document not found: {$job->document_id}");
            }
            
            // Update status menjadi processing
            $document->update(['status' => 'PROCESSING']);
            
            // Proses OCR
            $ocrResult = $this->documentService->processOCR($document);
            
            // Update status dan hasil
            $document->update([
                'status' => 'PROCESSED',
                'extracted_doc_number' => $ocrResult['doc_number'],
                'ocr_metadata' => json_encode($ocrResult['metadata']),
                'full_text_indexed' => false
            ]);
            
            // Tandai job sebagai selesai
            $job->update(['status' => 'COMPLETED']);
            
            // Kirim ke queue indexing
            $this->dispatchIndexingJob($document->id);
            
        } catch (Exception $e) {
            // Tangani error
            $this->handleJobError($job, $e);
        }
    }
    
    private function dispatchIndexingJob(int $documentId): void
    {
        // Implementasi dispatch job indexing ke Elasticsearch
        // Bisa menggunakan queue lain atau metode langsung
    }
    
    private function handleJobError($job, Exception $e): void
    {
        $maxTries = (int)($_ENV['WORKER_MAX_TRIES'] ?? 3);
        
        $job->increment('attempts');
        
        if ($job->attempts >= $maxTries) {
            // Pindahkan ke dead letter queue
            $job->update([
                'status' => 'FAILED',
                'failure_reason' => $e->getMessage()
            ]);
            
            // Log error
            error_log("Job failed after {$maxTries} attempts: " . $e->getMessage());
        } else {
            // Kembalikan ke queue untuk retry
            $job->update(['status' => 'PENDING']);
        }
    }
    
    private function shouldShutdown(): bool
    {
        // Implementasi pengecekan sinyal shutdown
        // Biasanya melalui file lock atau variabel environment
        return false;
    }
}

// Jalankan worker
$worker = new OCRWorker();
$worker->run();
```

## Manajemen Worker

### Menjalankan Worker
```bash
# Single worker instance
php workers/ocr-worker.php

# Multiple worker instances (dengan process manager)
# Contoh dengan supervisord
[program:ocr-worker]
process_name=%(program_name)s_%(process_num)02d
command=php workers/ocr-worker.php
autostart=true
autorestart=true
user=www-data
numprocs=3
redirect_stderr=true
stdout_logfile=/var/log/ocr-worker.log
```

### Monitoring Worker
```bash
# Cek status queue
php artisan queue:status

# Lihat job yang sedang berjalan
redis-cli llen queues:default

# Lihat job yang gagal
php artisan queue:failed

# Coba ulang job yang gagal
php artisan queue:retry all
```

## Skalabilitas Worker

### Auto Scaling Berdasarkan Queue Length
```php
// Skrip untuk auto scaling worker
function scaleWorkersBasedOnQueueLength(): void
{
    $queueLength = getRedisQueueLength();
    $currentWorkers = countRunningWorkers();
    
    $workersNeeded = ceil($queueLength / 10); // Asumsi 1 worker bisa handle 10 job
    $workersNeeded = max(1, min($workersNeeded, 10)); // Batasi 1-10 worker
    
    if ($workersNeeded > $currentWorkers) {
        // Tambah worker
        startNewWorkers($workersNeeded - $currentWorkers);
    } elseif ($workersNeeded < $currentWorkers) {
        // Kurangi worker
        stopExcessWorkers($currentWorkers - $workersNeeded);
    }
}
```

### Konfigurasi Horizontal Scaling
- Gunakan multiple worker instances di server yang berbeda
- Pastikan semua worker mengakses queue dan database yang sama
- Gunakan load balancer jika perlu
- Monitor resource usage untuk optimalisasi

## Penanganan Error dan Retry

### Dead Letter Queue (DLQ)
- Tempat untuk job yang gagal setelah beberapa kali retry
- Memungkinkan investigasi manual terhadap job bermasalah
- Bisa diimplementasikan sebagai tabel database terpisah

### Retry Mechanism
- **Exponential Backoff**: Waktu tunggu antar retry meningkat secara eksponensial
- **Max Retries**: Batas maksimal retry sebelum masuk DLQ
- **Failure Analysis**: Logging dan analisis penyebab kegagalan

## Performance Optimization

### Batch Processing
```php
// Proses beberapa job sekaligus untuk efisiensi
public function processBatch(int $batchSize = 10): void
{
    $jobs = DocumentOCRJob::where('status', 'PENDING')
                         ->limit($batchSize)
                         ->get();
    
    foreach ($jobs as $job) {
        $this->processJob($job);
    }
}
```

### Connection Pooling
- Gunakan connection pooling untuk database dan Redis
- Optimasi jumlah koneksi berdasarkan beban
- Monitor connection usage untuk optimalisasi

### Resource Management
- Batasi memory usage per worker
- Batasi jumlah job per worker (restart setelah jumlah tertentu)
- Gunakan timeout yang sesuai untuk mencegah job stuck

## Monitoring dan Observability

### Metrik Penting
- **Queue Length**: Jumlah job dalam antrian
- **Processing Rate**: Jumlah job yang diproses per detik
- **Failure Rate**: Persentase job yang gagal
- **Processing Time**: Rata-rata waktu pemrosesan per job
- **Worker Utilization**: Penggunaan resource oleh worker

### Alert Thresholds
- Queue length > 100: Alert tinggi
- Failure rate > 5%: Alert kritis
- Processing time > 5 menit: Alert kinerja
- Worker down: Alert kritis

## Troubleshooting

### Masalah Umum
1. **Queue bottleneck**
   - Penyebab: Jumlah worker tidak seimbang dengan jumlah job
   - Solusi: Tambah worker atau optimasi proses OCR

2. **Memory leak pada worker**
   - Penyebab: Akumulasi data di memory sepanjang proses
   - Solusi: Restart worker setelah jumlah job tertentu

3. **Job stuck**
   - Penyebab: Proses yang tidak selesai atau error tak tertangani
   - Solusi: Implementasi timeout dan penanganan error yang baik

### Command Troubleshooting
```bash
# Flush queue (dengan hati-hati)
php artisan queue:flush

# Restart semua worker
pkill -f ocr-worker.php

# Cek detail job spesifik
php artisan queue:show {job-id}

# Force delete job
php artisan queue:forget {job-id}
```

## Best Practices

### 1. Idempotency
- Pastikan job bisa dijalankan ulang tanpa efek samping
- Gunakan status dan pengecekan sebelum eksekusi

### 2. Graceful Shutdown
- Implementasi penanganan sinyal untuk shutdown aman
- Pastikan job saat ini selesai sebelum shutdown

### 3. Error Isolation
- Pisahkan error handling untuk setiap job
- Jangan biarkan satu job error menghentikan seluruh worker

### 4. Resource Management
- Batasi resource per worker
- Gunakan restart otomatis untuk mencegah memory bloat

## Kesimpulan

Manajemen queue dan worker adalah komponen kritis dalam sistem Document OCR & Archival System. Dengan arsitektur yang baik, konfigurasi yang tepat, dan monitoring yang komprehensif, sistem dapat menangani beban kerja berat OCR secara efisien dan handal.