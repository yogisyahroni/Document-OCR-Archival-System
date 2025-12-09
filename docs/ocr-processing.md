# OCR Processing & Document Number Extraction

## Ikhtisar
Dokumen ini menjelaskan proses OCR (Optical Character Recognition) dan ekstraksi nomor dokumen dalam sistem Document OCR & Archival System, yang merupakan komponen kritis untuk kemampuan pencarian dokumen.

## Arsitektur OCR Processing

### Komponen Utama
- **OCR Engine**: Tesseract atau layanan OCR lainnya untuk ekstraksi teks
- **Document Parser**: Komponen untuk memproses dokumen PDF dan gambar
- **Regex Engine**: Untuk mengidentifikasi dan mengekstrak nomor dokumen
- **Text Cleaner**: Untuk membersihkan dan memformat teks hasil OCR

### Alur Pemrosesan
```
┌─────────────────┐    ┌─────────────────┐    ┌─────────────────┐
│   Input File    │ -> │  Document       │ -> │   OCR Engine    │
│ (PDF/Image)     │    │  Parser         │    │  (Tesseract)    │
└─────────────────┘    └─────────────────┘    └─────────────────┘
         │                       │                       │
         ▼                       ▼                       ▼
┌─────────────────┐    ┌─────────────────┐    ┌─────────────────┐
│ Format Check    │ -> │ Page Processing │ -> │ Text Extraction │
│ & Validation    │    │ (Multi-page)    │    │ & Cleaning      │
└─────────────────┘    └─────────────────┘    └─────────────────┘
         │                       │                       │
         ▼                       ▼
┌─────────────────┐    ┌─────────────────┐    ┌─────────────────┐
│ Security Check  │ -> │ Metadata        │ -> │ Document Number │
│ (Malware, etc)  │    │ Extraction      │    │ Extraction      │
└─────────────────┘    └─────────────────┘    └─────────────────┘
```

## Implementasi OCR

### OCR Service Class
```php
// src/Services/OCRService.php
<?php

namespace App\Services;

use App\Models\Document;
use App\Exceptions\OCRProcessingException;
use Imagick;
use TesseractOCR;

class OCRService
{
    private array $config;
    
    public function __construct()
    {
        $this->config = [
            'tesseract_path' => env('TESSERACT_PATH', '/usr/bin/tesseract'),
            'languages' => env('OCR_LANGUAGES', 'eng+ind'),
            'document_patterns' => [
                '/\b(?:DOC|NO|NOMOR|NUMBER)\s*[-.:]?\s*([A-Z0-9]{2,}-[A-Z0-9]{2,}-[A-Z0-9]{2,})\b/i',
                '/\b([A-Z]{2,}-\d{4}-\d{3,})\b/',
                '/\b(?:REG|REGISTRATION)\s*[-.:]?\s*([A-Z0-9]{6,})\b/i',
                '/\b\d{2,}-[A-Z]{2,}-\d{4}\b/',
                '/\b[A-Z]{3,}\d{6,}\b/',
            ]
        ];
    }
    
    public function processDocument(Document $document): array
    {
        $result = [
            'document_id' => $document->id,
            'full_text' => '',
            'extracted_doc_number' => null,
            'confidence_score' => 0,
            'metadata' => [],
            'processing_time' => 0
        ];
        
        $startTime = microtime(true);
        
        try {
            // 1. Validasi dan parsing dokumen
            $pages = $this->parseDocument($document);
            
            // 2. Proses setiap halaman
            $fullText = '';
            $docNumbers = [];
            
            foreach ($pages as $pageIndex => $page) {
                $text = $this->extractTextFromPage($page);
                $fullText .= $text . "\n";
                
                // Cari nomor dokumen di teks halaman ini
                $pageDocNumbers = $this->extractDocumentNumbers($text);
                $docNumbers = array_merge($docNumbers, $pageDocNumbers);
            }
            
            // 3. Pilih nomor dokumen terbaik
            $bestDocNumber = $this->selectBestDocumentNumber($docNumbers);
            
            // 4. Hitung confidence score
            $confidenceScore = $this->calculateConfidenceScore($docNumbers, $fullText);
            
            $result['full_text'] = trim($fullText);
            $result['extracted_doc_number'] = $bestDocNumber;
            $result['confidence_score'] = $confidenceScore;
            $result['processing_time'] = microtime(true) - $startTime;
            
            return $result;
            
        } catch (\Exception $e) {
            throw new OCRProcessingException(
                "OCR processing failed for document {$document->id}: " . $e->getMessage(),
                0,
                $e
            );
        }
    }
    
    private function parseDocument(Document $document): array
    {
        $filePath = $this->downloadDocumentFile($document);
        
        // Deteksi tipe file
        $mimeType = mime_content_type($filePath);
        
        switch ($mimeType) {
            case 'application/pdf':
                return $this->parsePDF($filePath);
            case 'image/jpeg':
            case 'image/png':
            case 'image/tiff':
                return [$filePath]; // Single image
            default:
                throw new OCRProcessingException("Unsupported file type: {$mimeType}");
        }
    }
    
    private function parsePDF(string $pdfPath): array
    {
        // Gunakan Imagick untuk mengonversi PDF ke gambar per halaman
        $imagick = new Imagick();
        $imagick->readImage($pdfPath);
        
        $pages = [];
        $tempDir = sys_get_temp_dir() . '/ocr_temp_' . uniqid();
        mkdir($tempDir, 0755, true);
        
        for ($i = 0; $i < $imagick->getNumberImages(); $i++) {
            $imagick->setIteratorIndex($i);
            
            $pagePath = $tempDir . '/page_' . $i . '.png';
            $imagick->writeImage($pagePath);
            
            $pages[] = $pagePath;
        }
        
        return $pages;
    }
    
    private function extractTextFromPage(string $imagePath): string
    {
        // Gunakan Tesseract untuk ekstraksi teks
        $ocr = new TesseractOCR($imagePath);
        $ocr->lang($this->config['languages']);
        $ocr->psm(6); // Assume a single uniform block of text
        
        $text = $ocr->run();
        
        // Bersihkan teks
        $cleanedText = $this->cleanExtractedText($text);
        
        return $cleanedText;
    }
    
    private function extractDocumentNumbers(string $text): array
    {
        $docNumbers = [];
        
        foreach ($this->config['document_patterns'] as $pattern) {
            preg_match_all($pattern, $text, $matches);
            
            if (!empty($matches[1])) {
                foreach ($matches[1] as $match) {
                    $docNumbers[] = [
                        'number' => trim($match),
                        'pattern_used' => $pattern,
                        'position' => strpos($text, $match)
                    ];
                }
            }
        }
        
        return $docNumbers;
    }
    
    private function selectBestDocumentNumber(array $docNumbers): ?string
    {
        if (empty($docNumbers)) {
            return null;
        }
        
        // Prioritaskan nomor dokumen berdasarkan:
        // 1. Panjang (lebih panjang biasanya lebih spesifik)
        // 2. Format (lebih sesuai dengan pola yang diharapkan)
        // 3. Posisi (nomor di awal dokumen mungkin lebih penting)
        
        usort($docNumbers, function($a, $b) {
            // Cek panjang
            $lenA = strlen($a['number']);
            $lenB = strlen($b['number']);
            
            if ($lenA !== $lenB) {
                return $lenB - $lenA; // Lebih panjang lebih baik
            }
            
            // Cek posisi (lebih awal lebih baik)
            return $a['position'] - $b['position'];
        });
        
        return $docNumbers[0]['number'];
    }
    
    private function calculateConfidenceScore(array $docNumbers, string $fullText): float
    {
        if (empty($docNumbers)) {
            return 0.0;
        }
        
        // Hitung confidence berdasarkan:
        // 1. Jumlah kemungkinan nomor dokumen ditemukan
        // 2. Panjang rata-rata nomor dokumen
        // 3. Proporsi teks yang cocok dengan pola
        
        $totalNumbers = count($docNumbers);
        $avgLength = array_sum(array_map(fn($n) => strlen($n['number']), $docNumbers)) / $totalNumbers;
        $textLength = strlen($fullText);
        
        // Skor dasar berdasarkan jumlah dan panjang
        $baseScore = min(1.0, ($totalNumbers * $avgLength) / 100);
        
        // Tambahkan faktor berdasarkan proporsi teks yang cocok
        $matchedChars = 0;
        foreach ($docNumbers as $num) {
            $matchedChars += strlen($num['number']);
        }
        
        $matchRatio = $matchedChars / max(1, $textLength);
        $matchScore = min(0.5, $matchRatio * 2); // Maksimal 0.5 dari total skor
        
        return min(1.0, $baseScore + $matchScore);
    }
    
    private function cleanExtractedText(string $text): string
    {
        // Hapus karakter aneh dan whitespace berlebihan
        $text = preg_replace('/\s+/', ' ', $text); // Gabungkan whitespace
        $text = preg_replace('/[^\x20-\x7E\x{00A0}-\x{024F}\x{1E00}-\x{1EFF}]/u', ' ', $text); // Hanya karakter yang valid
        $text = trim($text);
        
        return $text;
    }
    
    private function downloadDocumentFile(Document $document): string
    {
        // Implementasi download dari S3/GCS
        // Kode ini akan bergantung pada konfigurasi storage
        $s3Client = new \Aws\S3\S3Client([
            'version' => 'latest',
            'region' => env('AWS_DEFAULT_REGION'),
            'credentials' => [
                'key' => env('AWS_ACCESS_KEY_ID'),
                'secret' => env('AWS_SECRET_ACCESS_KEY'),
            ],
        ]);
        
        $tempPath = sys_get_temp_dir() . '/' . uniqid('ocr_', true);
        
        $result = $s3Client->getObject([
            'Bucket' => env('AWS_BUCKET'),
            'Key' => $document->s3_path,
        ]);
        
        file_put_contents($tempPath, $result['Body']);
        
        return $tempPath;
    }
}
```

## Regex Patterns untuk Ekstraksi Nomor Dokumen

### Pola Umum yang Didukung
1. **Format Klasik**: `DOC-2023-01`
2. **Format Registrasi**: `REG123456`
3. **Format Tanggal**: `2023/DOC/01`
4. **Format Kombinasi**: `ABCD123456`

### Implementasi Regex
```php
// Konfigurasi pola regex dalam file config/ocr.php
return [
    'document_patterns' => [
        // Format DOC-YYYY-NNN
        '/\bDOC-\d{4}-\d{3,}\b/',
        
        // Format REG-NNNNNN
        '/\bREG-\d{6,}\b/',
        
        // Format dengan kata kunci
        '/\b(?:NO|NOMOR|NUMBER)\s*[-.:]?\s*([A-Z0-9]{2,}-[A-Z0-9]{2,}-[A-Z0-9]{2,})\b/i',
        
        // Format tahun/dokumen/angka
        '/\b\d{4}\/[A-Z]{3,}\/\d{3,}\b/',
        
        // Format huruf besar + angka
        '/\b[A-Z]{3,}\d{6,}\b/',
        
        // Format huruf-angka-huruf
        '/\b[A-Z]{2,}-\d{4}-[A-Z]{2,}\b/',
    ],
    
    'validation_rules' => [
        'min_length' => 6,
        'max_length' => 50,
        'required_chars' => ['A-Z', '0-9'],
        'forbidden_patterns' => [
            '/\b(0+)\b/', // Hanya angka nol
            '/\b(\d)\1{5,}\b/', // Angka berulang
        ],
    ],
];
```

## Optimasi OCR

### Preprocessing Gambar
```php
private function preprocessImage(string $imagePath): string
{
    $imagick = new Imagick($imagePath);
    
    // 1. Konversi ke grayscale
    $imagick->setImageColorspace(\Imagick::COLORSPACE_GRAY);
    
    // 2. Tambahkan kontras
    $imagick->enhanceImage();
    
    // 3. Resize jika terlalu besar (untuk kualitas OCR)
    $imagick->resizeImage(2000, 2000, \Imagick::FILTER_LANCZOS, 1, true);
    
    // 4. Sharpen sedikit
    $imagick->sharpenImage(1, 1);
    
    $processedPath = $imagePath . '.processed.png';
    $imagick->writeImage($processedPath);
    
    return $processedPath;
}
```

### Penggunaan Tesseract Options
```php
private function extractTextFromPageOptimized(string $imagePath): string
{
    $ocr = new TesseractOCR($imagePath);
    
    // Set options untuk kualitas OCR yang lebih baik
    $ocr->lang($this->config['languages'])
        ->psm(6) // Single uniform block of text
        ->oem(3) // Default, based on what is available
        ->config(['tessedit_char_whitelist' => 'ABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789 -./']);
    
    return $ocr->run();
}
```

## Penanganan Error dan Kualitas

### Validasi Hasil OCR
```php
private function validateOCRResult(string $docNumber, string $fullText): bool
{
    if (empty($docNumber)) {
        return false;
    }
    
    // Cek panjang
    $minLength = config('ocr.validation_rules.min_length', 6);
    $maxLength = config('ocr.validation_rules.max_length', 50);
    
    $length = strlen($docNumber);
    if ($length < $minLength || $length > $maxLength) {
        return false;
    }
    
    // Cek karakter yang diperbolehkan
    $requiredChars = config('ocr.validation_rules.required_chars', []);
    foreach ($requiredChars as $charPattern) {
        if (!preg_match("/[{$charPattern}]/", $docNumber)) {
            return false;
        }
    }
    
    // Cek pola yang dilarang
    $forbiddenPatterns = config('ocr.validation_rules.forbidden_patterns', []);
    foreach ($forbiddenPatterns as $pattern) {
        if (preg_match($pattern, $docNumber)) {
            return false;
        }
    }
    
    return true;
}
```

### Confidence Scoring
```php
private function calculateAdvancedConfidenceScore(array $ocrResult): float
{
    $baseScore = $ocrResult['confidence_score'];
    
    // Faktor tambahan berdasarkan:
    // 1. Jumlah halaman yang diproses
    $pageCountFactor = min(1.0, count($ocrResult['pages']) / 10);
    
    // 2. Kualitas teks (jumlah karakter valid)
    $validCharRatio = $this->calculateValidCharacterRatio($ocrResult['full_text']);
    
    // 3. Konsistensi format nomor dokumen
    $formatConsistency = $this->calculateFormatConsistency($ocrResult['document_numbers']);
    
    // Gabungkan semua faktor
    $finalScore = ($baseScore * 0.5) + 
                  ($pageCountFactor * 0.1) + 
                  ($validCharRatio * 0.2) + 
                  ($formatConsistency * 0.2);
    
    return min(1.0, max(0.0, $finalScore));
}
```

## Skalabilitas dan Performansi

### Batch Processing
```php
public function processBatch(array $documentIds): array
{
    $results = [];
    
    foreach ($documentIds as $docId) {
        try {
            $document = Document::find($docId);
            if ($document) {
                $result = $this->processDocument($document);
                $results[] = $result;
            }
        } catch (\Exception $e) {
            // Log error dan lanjutkan ke dokumen berikutnya
            error_log("OCR batch processing error for doc {$docId}: " . $e->getMessage());
        }
    }
    
    return $results;
}
```

### Caching Hasil
```php
private function getCachedResult(Document $document): ?array
{
    $cacheKey = "ocr_result_{$document->id}";
    return cache()->get($cacheKey);
}

private function cacheResult(Document $document, array $result): void
{
    $cacheKey = "ocr_result_{$document->id}";
    cache()->set($cacheKey, $result, 3600 * 24); // Cache 24 jam
}
```

## Monitoring dan Observability

### Metrik OCR
- **Processing Time**: Rata-rata waktu pemrosesan per dokumen
- **Success Rate**: Persentase dokumen yang berhasil diproses
- **Confidence Score Distribution**: Distribusi skor kepercayaan
- **Document Number Quality**: Kualitas hasil ekstraksi nomor dokumen

### Logging
```php
// Log hasil OCR dengan detail
log_info('OCR Processing Complete', [
    'document_id' => $result['document_id'],
    'processing_time' => $result['processing_time'],
    'confidence_score' => $result['confidence_score'],
    'extracted_doc_number' => $result['extracted_doc_number'],
    'full_text_length' => strlen($result['full_text']),
    'pages_processed' => count($result['pages'] ?? [])
]);
```

## Kesimpulan

Proses OCR dan ekstraksi nomor dokumen merupakan komponen kritis dalam sistem Document OCR & Archival System. Dengan implementasi yang tepat, termasuk preprocessing gambar, penggunaan pola regex yang akurat, dan penanganan error yang baik, sistem dapat mencapai akurasi tinggi dalam mengidentifikasi dan mengekstrak informasi penting dari dokumen.