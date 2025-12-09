# Document OCR & Archival System

Sistem manajemen dokumen terdistribusi dan asinkron yang dirancang untuk menyimpan, mengindeks, dan mencari dokumen digital (terutama PDF) secara efisien berdasarkan metadata dan nomor dokumen yang diekstrak otomatis melalui OCR.

## Ikhtisar

Sistem ini adalah solusi manajemen dokumen yang menggunakan arsitektur berbasis antrean untuk menangani beban kerja berat OCR secara *background*. Tujuan utamanya adalah menyimpan, mengindeks, dan mencari dokumen digital secara efisien berdasarkan metadata dan nomor dokumen yang diekstrak melalui OCR.

### Fitur Utama

- **Autentikasi & Otorisasi**: Sistem login pengguna dan kontrol akses berbasis peran (RBAC)
- **Unggah Dokumen**: API endpoint untuk menerima file (PDF) dan metadata awal
- **Pemrosesan Asinkron**: Ekstraksi teks dan OCR dilakukan di background melalui queue
- **Pencarian Cepat**: Mampu mencari dokumen berdasarkan keyword, tanggal, dan nomor dokumen
- **Audit Trail**: Mencatat user yang mengunggah dan memodifikasi dokumen

## Arsitektur

Sistem ini dibangun dengan pendekatan microservices dan menggunakan berbagai teknologi untuk menangani skala tinggi dan kinerja optimal:

| Layer | Teknologi | Alasan |
|-------|-----------|---------|
| **Aplikasi Web (API)** | **FrankenPHP + Symfony/Laravel** | Kinerja tinggi, dukungan worker proses, dan framework siap produksi |
| **Web Server** | **Caddy (Built-in FrankenPHP)** | Pengganti Nginx/Apache yang modern, efisien, dan mendukung HTTP/3 |
| **Database Primer (Metadata)** | **PostgreSQL (v14+)** | Reliable, dukungan ACID transaction, dan kemampuan indexing yang kuat |
| **Object Storage (File)** | **AWS S3 / Google Cloud Storage (GCS)** | Penyimpanan file yang sangat terukur, tahan lama, dan biaya-efektif |
| **Antrean/Queue Broker** | **Redis** | Broker in-memory berkecepatan tinggi untuk job asinkron |
| **OCR & Index Worker** | **Dedicated PHP Workers** | Proses daemon yang terpisah dari web process untuk memisahkan beban komputasi berat |
| **Pencarian Teks Penuh** | **Elasticsearch / OpenSearch** | Mesin pencari terdistribusi untuk pencarian full-text dan fuzzy matching nomor dokumen |
| **Monitoring/Logging** | **Prometheus/Grafana + ELK Stack/Loki** | Untuk observability (melacak worker lag, request latency, dan debugging jobs) |

## Alur Proses

1. **Request**: Client mengirim `POST /api/documents`
2. **API Gateway (FrankenPHP)**: Memvalidasi request. Menyimpan file ke S3/GCS
3. **Database Write**: Mencatat `document` di PostgreSQL dengan `status = PENDING`
4. **Queue Dispatch**: API mengirim message `DocumentIngestJob(document_id)` ke **Redis Queue**
5. **Response**: API mengembalikan `HTTP 202 Accepted` (pemrosesan sedang berlangsung)
6. **Worker Consumption**: PHP Worker mengambil job dari antrean
7. **OCR Execution**: Worker mengunduh file dari S3 dan menjalankan OCR
8. **RegEx Extraction**: Worker menerapkan RegEx untuk mengidentifikasi `extracted_doc_number`
9. **Database Update**: Worker memperbarui `documents.status = PROCESSED` dan `documents.extracted_doc_number` di PostgreSQL
10. **Index Write**: Worker mengirim full-text hasil OCR dan metadata ke **Elasticsearch**

## Struktur Proyek

```
Document-OCR-Archival-System/
├── src/
│   ├── Models/           # Model-model domain
│   ├── DTOs/             # Data Transfer Objects
│   ├── Services/         # Business logic
│   ├── Repositories/     # Data access layer
│   ├── Config/           # Konfigurasi aplikasi
│   ├── Exceptions/       # Custom exceptions
│   ├── Utils/            # Utilitas umum
│   └── Workers/          # Background workers
├── config/               # File-file konfigurasi
├── database/
│   └── migrations/       # Skrip migrasi database
├── public/               # Public web root
├── scripts/              # Skrip pendukung
├── workers/              # Background worker processes
├── docker/               # Konfigurasi Docker
├── docker-compose.yml    # Docker Compose untuk deployment
├── Dockerfile            # Docker image untuk aplikasi
├── .env.example          # Contoh file environment
├── composer.json         # Dependency management
└── README.md             # Dokumentasi ini
```

## Skema Database

### Tabel `documents`

| Kolom | Tipe Data | Constraint | Keterangan |
|-------|-----------|------------|------------|
| `id` | `UUID` | `PRIMARY KEY` | Menghindari hotspot ID serial dan memudahkan sharding |
| `uploaded_by_id` | `BIGINT` | `NOT NULL`, `FOREIGN KEY` | Audit dan kepemilikan |
| `s3_path` | `TEXT` | `NOT NULL`, `UNIQUE` | Lokasi file absolut di object storage |
| `title` | `VARCHAR(255)` | `NOT NULL` | Metadata yang diinput pengguna |
| `status` | `VARCHAR(20)` | `NOT NULL` | `PENDING`, `PROCESSING`, `PROCESSED`, `FAILED` |
| `extracted_doc_number` | `VARCHAR(10)` | **INDEX** | Target utama pencarian cepat |
| `full_text_indexed` | `BOOLEAN` | `NOT NULL` | Status sinkronisasi ke Elasticsearch |
| `ocr_metadata` | `JSONB` | `NULLABLE` | Untuk confidence scores atau engine version |
| `created_at` | `TIMESTAMPTZ` | `NOT NULL` | |

### Tabel `document_categories`

| Kolom | Tipe Data | Constraint |
|-------|-----------|------------|
| `id` | `INT` | `PRIMARY KEY` |
| `name` | `VARCHAR(100)` | `NOT NULL` |

### Tabel `users`

| Kolom | Tipe Data | Constraint |
|-------|-----------|------------|
| `id` | `BIGSERIAL` | `PRIMARY KEY` |
| `email` | `VARCHAR(255)` | `UNIQUE`, `NOT NULL` |
| `password_hash` | `VARCHAR(255)` | `NOT NULL` (Argon2) |

## Setup Development

### Prerequisites

- PHP 8.1+
- Composer
- Docker & Docker Compose
- PostgreSQL
- Redis
- Elasticsearch

### Instalasi

1. Clone repository:
```bash
git clone <repository-url>
cd Document-OCR-Archival-System
```

2. Install dependensi:
```bash
composer install
```

3. Buat file environment:
```bash
cp .env.example .env
```

4. Konfigurasi file `.env` sesuai kebutuhan

5. Jalankan migrasi database:
```bash
php artisan migrate
```

6. Jalankan aplikasi dengan Docker:
```bash
docker-compose up -d
```

## API Endpoints

- `POST /api/documents` - Upload dokumen baru
- `GET /api/documents` - List semua dokumen
- `GET /api/documents/{id}` - Detail dokumen
- `GET /api/search` - Cari dokumen berdasarkan keyword
- `POST /api/auth/login` - Login pengguna
- `POST /api/auth/register` - Registrasi pengguna baru

## Konfigurasi Environment

File `.env` berisi konfigurasi penting:

```env
APP_NAME="Document OCR System"
APP_ENV=local
APP_KEY=
APP_DEBUG=true
APP_TIMEZONE=UTC
APP_URL=http://localhost

DB_CONNECTION=pgsql
DB_HOST=127.0.0.1
DB_PORT=5432
DB_DATABASE=document_ocr
DB_USERNAME=postgres
DB_PASSWORD=

REDIS_HOST=127.0.0.1
REDIS_PASSWORD=null
REDIS_PORT=6379

ELASTICSEARCH_HOST=localhost
ELASTICSEARCH_PORT=9200

AWS_ACCESS_KEY_ID=
AWS_SECRET_ACCESS_KEY=
AWS_DEFAULT_REGION=us-east-1
AWS_BUCKET=your-bucket-name

S3_UPLOAD_PATH=documents/
```

## Deployment

Sistem ini dirancang untuk deployment dengan Docker dan Docker Compose. File `docker-compose.yml` menyertakan:

- FrankenPHP (sebagai web server)
- PostgreSQL
- Redis
- Elasticsearch
- Worker processes

Untuk deployment produksi:

1. Pastikan semua konfigurasi environment disetel dengan benar
2. Jalankan `docker-compose up -d` untuk memulai semua layanan
3. Pastikan monitoring dan logging aktif (Prometheus/Grafana)

## Keamanan

Sistem ini mengimplementasikan Zero Trust Architecture:

- Enkripsi data saat transit (HTTPS/TLS)
- Enkripsi data saat disimpan (PostgreSQL/S3 encryption)
- Validasi input yang ketat
- Autentikasi JWT
- Pembatasan akses berbasis peran
- Tidak menyimpan dokumen sensitif di disk server

## Monitoring dan Observability

- **Prometheus/Grafana**: Untuk metrik sistem dan kinerja
- **Loki**: Untuk log agregasi
- **Elasticsearch**: Untuk log dan pencarian teks penuh
- **Custom metrics**: Untuk melacak worker lag, request latency, dan debugging jobs

## Testing

Sistem ini menggunakan pendekatan testing pyramid:

- **Unit Testing**: 70% - Fungsi-fungsi individual dan business logic
- **Integration Testing**: 20% - API endpoints dan database queries
- **E2E Testing**: 10% - User journeys kritis

## Skalabilitas

- **Horizontal Scaling**: Worker dan search node dapat ditambahkan secara horizontal
- **Queue-based Architecture**: Memungkinkan pemrosesan paralel
- **Object Storage**: Menggunakan S3/GCS untuk menyimpan file secara skalabel
- **Database Sharding**: Mendukung sharding untuk data dokumen

## Kesimpulan

Sistem Document OCR & Archival System adalah solusi lengkap untuk manajemen dokumen digital dengan kemampuan OCR dan pencarian yang kuat. Dibangun dengan pendekatan microservices, sistem ini dapat menangani volume besar dokumen dengan kinerja tinggi dan skalabilitas yang baik.