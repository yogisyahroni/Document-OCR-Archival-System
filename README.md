# Document OCR & Archival System

Sistem manajemen dokumen terdistribusi dan asinkron yang dirancang untuk menyimpan, mengindeks, dan mencari dokumen digital (terutama PDF) secara efisien berdasarkan metadata dan nomor dokumen yang diekstraksi otomatis melalui OCR.

## Deskripsi Aplikasi

Document OCR & Archival System adalah solusi manajemen dokumen berbasis web yang memungkinkan pengguna untuk mengunggah dokumen dalam berbagai format (PDF, JPEG, PNG, TIFF), melakukan pemrosesan OCR (Optical Character Recognition) secara otomatis, dan menyimpan hasilnya dalam sistem yang dapat dicari. Sistem ini dirancang untuk memastikan skalabilitas dan kinerja tinggi melalui pendekatan arsitektur terdistribusi dan asinkron.

Fitur utama sistem ini meliputi:
- Upload dokumen dengan berbagai format
- Pemrosesan OCR otomatis untuk ekstraksi teks
- Pencarian cerdas berdasarkan konten dokumen dan nomor dokumen
- Sistem kategorisasi dokumen
- Antarmuka pengguna yang intuitif
- Otentikasi dan otorisasi berbasis peran
- Monitoring dan logging komprehensif

## Kegunaan Aplikasi

Aplikasi ini berguna untuk berbagai kebutuhan bisnis dan organisasi, antara lain:

1. **Arsip Digital**: Mengkonversi dokumen fisik atau digital ke dalam format yang dapat dicari dan diakses secara efisien
2. **Manajemen Dokumen**: Mengelola ribuan dokumen dengan sistem kategorisasi dan pencarian yang canggih
3. **Pemrosesan Otomatis**: Melakukan ekstraksi nomor dokumen dan informasi penting lainnya secara otomatis
4. **Kepatuhan Regulator**: Menyimpan dan mengelola dokumen untuk keperluan audit dan kepatuhan
5. **Efisiensi Operasional**: Mengurangi waktu pencarian dokumen dan meningkatkan produktivitas tim

## Cara Kerja Aplikasi

### Arsitektur Sistem

Sistem ini menggunakan pendekatan arsitektur mikroservis dengan komponen-komponen berikut:

```
┌─────────────────┐    ┌─────────────────┐    ┌─────────────────┐
│   Web Client    │────│   API Gateway   │────│  PostgreSQL   │
│ (React/Vue)     │    │ (FrankenPHP +  │    │ (Metadata)     │
└─────────────────┘    │  Symfony/Laravel)│    └─────────────────┘
                      └─────────────────┘
                              │
                      ┌─────────────────┐
                      │    Redis        │
                      │  (Queue Broker) │
                      └─────────────────┘
                              │
        ┌─────────────────────┼─────────────────────┐
        │                     │                     │
┌─────────────────┐ ┌─────────────────┐ ┌─────────────────┐
│  OCR Workers    │ │  Elasticsearch  │ │  Object Store   │
│  (PHP Daemon)   │ │ (Full-text      │ │ (S3/GCS)       │
│                 │ │  Search)        │ │ (Documents)     │
└─────────────────┘ └─────────────────┘ └─────────────────┘
```

### Alur Proses

1. **Upload Dokumen**:
   - Pengguna mengunggah dokumen melalui antarmuka web
   - File disimpan di Object Storage (AWS S3 atau Google Cloud Storage)
   - Metadata dokumen disimpan di PostgreSQL dengan status "PENDING"
   - Job OCR ditambahkan ke antrean Redis

2. **Pemrosesan Asinkron**:
   - Worker PHP mengambil job dari antrean
   - Worker mengunduh file dari Object Storage
   - OCR dijalankan untuk mengekstrak teks dari dokumen
   - Regex digunakan untuk mengidentifikasi nomor dokumen
   - Hasil OCR dan nomor dokumen disimpan kembali ke PostgreSQL
   - Teks penuh diindeks ke Elasticsearch untuk pencarian

3. **Pencarian Dokumen**:
   - Pengguna dapat mencari dokumen berdasarkan kata kunci, tanggal, atau nomor dokumen
   - Pencarian dilakukan di Elasticsearch untuk hasil yang cepat dan akurat
   - Hasil pencarian dikembalikan melalui API ke antarmuka web

### Teknologi yang Digunakan

| Komponen | Teknologi |
|----------|-----------|
| Runtime | FrankenPHP + PHP 8.2+ |
| Web Server | Caddy (Built-in FrankenPHP) |
| Framework | Symfony 6.4+ / Laravel 10+ |
| Database | PostgreSQL 14+ (Metadata) |
| Object Storage | AWS S3 / Google Cloud Storage |
| Queue Broker | Redis |
| OCR Engine | Tesseract / Custom PHP Workers |
| Full-text Search | Elasticsearch / OpenSearch |
| Monitoring | Prometheus / Grafana + ELK Stack |

### Instalasi dan Konfigurasi

1. Clone repositori:
   ```bash
   git clone https://github.com/antigraviti/document-ocr-archival.git
   cd document-ocr-archival
   ```

2. Instal dependensi:
   ```bash
   composer install
   npm install
   ```

3. Buat file konfigurasi environment:
   ```bash
   cp .env.example .env
   ```

4. Atur konfigurasi database dan layanan eksternal di `.env`

5. Jalankan migrasi database:
   ```bash
   php artisan migrate
   ```

6. Jalankan aplikasi:
   ```bash
   # Jalankan server API
   php -S localhost:8000 -t public
   
   # Atau jika menggunakan FrankenPHP
   FRANKENPHP_CONFIG="index.php" frankenphp server
   ```

7. Jalankan worker OCR:
   ```bash
   php workers/ocr-worker.php
   ```

### Struktur Proyek

```
document-ocr-archival/
├── app/                    # Core application logic
│   ├── Controllers/        # Request handlers
│   ├── Models/             # Data models
│   ├── Services/           # Business logic
│   └── Repositories/       # Data access layer
├── config/                 # Configuration files
├── database/               # Migrations and seeds
├── public/                 # Web root
├── src/                    # Shared libraries
├── tests/                  # Test files
├── workers/                # Background job processors
├── storage/                # Temporary files
└── vendor/                 # Composer dependencies
```

### Kontribusi

Kontribusi sangat dihargai. Silakan ikuti langkah-langkah berikut untuk berkontribusi:

1. Fork repositori
2. Buat branch fitur (`git checkout -b feature/amazing-feature`)
3. Commit perubahan (`git commit -m 'Add amazing feature'`)
4. Push ke branch (`git push origin feature/amazing-feature`)
5. Buat pull request

Pastikan untuk mengikuti pedoman pengembangan dan menjalankan semua tes sebelum membuat pull request.

### Lisensi

Proyek ini dilisensikan di bawah lisensi MIT - lihat file [LICENSE](LICENSE) untuk detail lebih lanjut.