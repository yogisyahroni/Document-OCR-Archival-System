# Deployment Guide - Document OCR & Archival System

## Ikhtisar
Dokumen ini menjelaskan proses deployment sistem Document OCR & Archival System ke lingkungan produksi, termasuk konfigurasi, persiapan, dan best practices untuk menjaga keandalan dan kinerja sistem.

## Persiapan Deployment

### Prasyarat Infrastruktur
Sebelum deployment, pastikan infrastruktur berikut tersedia:

#### Server Requirements
- **CPU**: Minimum 4 core, direkomendasikan 8+ core untuk beban tinggi
- **RAM**: Minimum 8GB, direkomendasikan 16GB+ untuk worker dan database
- **Storage**: SSD untuk database dan cache, serta akses ke object storage (S3/GCS)
- **OS**: Ubuntu 20.04+ atau CentOS 8+ (didukung oleh FrankenPHP)

#### Layanan Eksternal
- **PostgreSQL**: Versi 14+ dengan konfigurasi produksi
- **Redis**: Untuk queue dan caching
- **Elasticsearch/OpenSearch**: Untuk pencarian teks penuh
- **Object Storage**: AWS S3 atau Google Cloud Storage
- **Monitoring**: Prometheus, Grafana, dan Loki untuk observability

### Konfigurasi Lingkungan
File `.env` harus berisi konfigurasi produksi yang aman:

```env
APP_NAME="Document OCR System"
APP_ENV=production
APP_DEBUG=false
APP_URL=https://your-domain.com
APP_TIMEZONE=UTC

# Database
DB_CONNECTION=pgsql
DB_HOST=your-postgres-host
DB_PORT=5432
DB_DATABASE=your_database_name
DB_USERNAME=your_username
DB_PASSWORD=your_secure_password

# Redis
REDIS_HOST=your-redis-host
REDIS_PASSWORD=your_redis_password
REDIS_PORT=6379

# Elasticsearch
ELASTICSEARCH_HOST=your-es-host
ELASTICSEARCH_PORT=9200

# Object Storage
AWS_ACCESS_KEY_ID=your_aws_access_key
AWS_SECRET_ACCESS_KEY=your_aws_secret_key
AWS_DEFAULT_REGION=your_region
AWS_BUCKET=your-bucket-name

# Queue
QUEUE_CONNECTION=redis

# Logging
LOG_CHANNEL=stack
LOG_STACK=errorlog,loki
LOKI_URL=your_loki_endpoint

# Security
JWT_SECRET=your_jwt_secret_key
```

## Deployment dengan Docker

### Struktur Docker Compose
File `docker-compose.yml` untuk produksi:

```yaml
version: '3.8'

services:
  app:
    build:
      context: .
      dockerfile: Dockerfile
    container_name: doc-ocr-app
    restart: unless-stopped
    environment:
      - APP_ENV=production
      - APP_DEBUG=false
    env_file:
      - .env
    volumes:
      - ./logs:/app/logs
    depends_on:
      - postgres
      - redis
      - elasticsearch
    networks:
      - app-network
    deploy:
      replicas: 2
      resources:
        limits:
          memory: 2G
          cpus: '1.0'
        reservations:
          memory: 1G
          cpus: '0.5'

  worker:
    build:
      context: .
      dockerfile: Dockerfile
    container_name: doc-ocr-worker
    restart: unless-stopped
    environment:
      - APP_ENV=production
    env_file:
      - .env
    command: php workers/ocr-worker.php
    depends_on:
      - postgres
      - redis
      - elasticsearch
    networks:
      - app-network
    deploy:
      replicas: 3
      resources:
        limits:
          memory: 1G
          cpus: '0.5'
        reservations:
          memory: 512M
          cpus: '0.25'

  postgres:
    image: postgres:14-alpine
    container_name: doc-ocr-postgres
    restart: unless-stopped
    environment:
      POSTGRES_DB: ${DB_DATABASE}
      POSTGRES_USER: ${DB_USERNAME}
      POSTGRES_PASSWORD: ${DB_PASSWORD}
    volumes:
      - postgres_data:/var/lib/postgresql/data
      - ./init.sql:/docker-entrypoint-initdb.d/init.sql
    ports:
      - "5432:5432"
    networks:
      - app-network
    deploy:
      resources:
        limits:
          memory: 4G
          cpus: '1.5'
        reservations:
          memory: 2G
          cpus: '0.75'

  redis:
    image: redis:7-alpine
    container_name: doc-ocr-redis
    restart: unless-stopped
    command: redis-server --requirepass ${REDIS_PASSWORD}
    volumes:
      - redis_data:/data
    networks:
      - app-network
    deploy:
      resources:
        limits:
          memory: 1G
          cpus: '0.5'
        reservations:
          memory: 512M
          cpus: '0.25'

  elasticsearch:
    image: docker.elastic.co/elasticsearch/elasticsearch:8.11.0
    container_name: doc-ocr-elasticsearch
    restart: unless-stopped
    environment:
      - discovery.type=single-node
      - xpack.security.enabled=false
      - "ES_JAVA_OPTS=-Xms2g -Xmx2g"
    volumes:
      - es_data:/usr/share/elasticsearch/data
    networks:
      - app-network
    deploy:
      resources:
        limits:
          memory: 4G
          cpus: '1.5'
        reservations:
          memory: 2G
          cpus: '0.75'

  caddy:
    image: caddy:2-alpine
    container_name: doc-ocr-caddy
    restart: unless-stopped
    ports:
      - "80:80"
      - "443:443"
    volumes:
      - ./Caddyfile:/etc/caddy/Caddyfile
      - caddy_data:/data
      - caddy_config:/config
    depends_on:
      - app
    networks:
      - app-network

networks:
  app-network:
    driver: bridge

volumes:
  postgres_data:
 redis_data:
  es_data:
  caddy_data:
  caddy_config:
```

### File Caddy
File konfigurasi Caddy (`Caddyfile`):

```
your-domain.com {
    encode zstd gzip

    reverse_proxy app:8000 {
        transport http {
            tls
        }
    }

    log {
        output file /var/log/caddy/access.log
        format single_field common_log
    }

    header {
        Strict-Transport-Security "max-age=31536000; includeSubDomains; preload"
        X-Content-Type-Options nosniff
        X-Frame-Options DENY
        X-XSS-Protection "1; mode=block"
        Referrer-Policy strict-origin-when-cross-origin
    }
}
```

## Proses Deployment

### 1. Persiapan Kode
```bash
# Pull kode terbaru
git pull origin main

# Install dependensi
composer install --no-dev --optimize-autoloader

# Generate application key jika belum ada
php artisan key:generate --force
```

### 2. Migrasi Database
```bash
# Backup database sebelum migrasi
pg_dump -h your-host -U your-user -d your-db > backup-$(date +%Y%m%d).sql

# Jalankan migrasi
php artisan migrate --force
```

### 3. Inisialisasi Elasticsearch
```bash
# Buat index Elasticsearch untuk dokumen
php scripts/create_elasticsearch_index.php
```

### 4. Deploy dengan Docker
```bash
# Build dan start service
docker-compose up -d --build

# Tunggu beberapa saat dan cek status
docker-compose ps
```

### 5. Verifikasi Deployment
```bash
# Cek log aplikasi
docker-compose logs app

# Cek log worker
docker-compose logs worker

# Test endpoint API
curl -X GET https://your-domain.com/api/health
```

## Konfigurasi Produksi

### Optimasi Database
```sql
-- Tambahkan index untuk kolom pencarian utama
CREATE INDEX CONCURRENTLY idx_documents_extracted_doc_number 
ON documents(extracted_doc_number) WHERE status = 'PROCESSED';

CREATE INDEX CONCURRENTLY idx_documents_status 
ON documents(status);

CREATE INDEX CONCURRENTLY idx_documents_created_at 
ON documents(created_at);
```

### Konfigurasi Queue
- Atur jumlah worker sesuai kapasitas server
- Gunakan dead letter queue untuk job yang gagal
- Implementasikan retry mechanism dengan backoff

### Konfigurasi Caching
- Gunakan Redis untuk caching sesi dan data sementara
- Atur TTL yang sesuai untuk berbagai jenis cache
- Implementasikan cache invalidation strategy

## Monitoring dan Observability

### Metrik yang Dipantau
- **Aplikasi**: Request rate, error rate, response time
- **Database**: Query performance, connection count, slow queries
- **Queue**: Job processing rate, queue length, failed jobs
- **OCR Worker**: Processing time, success rate, resource usage
- **Search**: Query performance, index size, memory usage

### Alert yang Diaktifkan
- High error rate (>5% dalam 5 menit)
- Slow response time (>2 detik rata-rata)
- Database connection pool penuh
- Queue backlog meningkat pesat
- Worker failure rate tinggi

### Logging Strategy
- Gunakan structured logging (JSON format)
- Filter dan redact data sensitif dari log
- Gunakan log level yang sesuai (info, warning, error)
- Kirim log ke sistem agregasi (Loki)

## Best Practices Produksi

### Keamanan
- Pastikan SSL/TLS aktif untuk semua komunikasi
- Gunakan JWT dengan expiration time yang sesuai
- Aktifkan rate limiting untuk mencegah abuse
- Jaga secrets dengan aman (tidak dalam kode)

### Kinerja
- Gunakan CDN untuk static assets
- Optimasi query database dengan index yang tepat
- Gunakan connection pooling
- Aktifkan compression untuk response

### Skalabilitas
- Gunakan horizontal scaling untuk worker
- Pisahkan read/write database jika perlu
- Gunakan load balancer untuk multiple app instances
- Monitor resource usage untuk perencanaan scaling

## Troubleshooting

### Masalah Umum
1. **Worker tidak memproses job**
   - Cek koneksi Redis
   - Cek status queue
   - Periksa log worker

2. **OCR processing lambat**
   - Cek resource usage
   - Periksa koneksi ke object storage
   - Monitor Elasticsearch performance

3. **High memory usage**
   - Periksa konfigurasi FrankenPHP worker
   - Monitor jumlah koneksi database
   - Optimasi proses OCR

### Command Utilitas
```bash
# Cek status queue
php artisan queue:status

# Restart semua worker
docker-compose restart worker

# Flush queue jika ada masalah
php artisan queue:flush

# Cek log error
docker-compose logs --tail=100 app | grep ERROR
```

## Rollback Prosedur

Jika deployment bermasalah:

1. **Kembali ke versi sebelumnya**
```bash
git checkout <previous-commit-hash>
docker-compose build --no-cache
docker-compose up -d
```

2. **Kembalikan database jika perlu**
```bash
psql -h your-host -U your-user -d your-db < backup-file.sql
```

3. **Restart semua service**
```bash
docker-compose down
docker-compose up -d
```

## Kesimpulan

Deployment sistem Document OCR & Archival System memerlukan perhatian khusus terhadap konfigurasi keamanan, kinerja, dan skalabilitas. Dengan mengikuti panduan ini, sistem dapat dijalankan dengan aman dan efisien di lingkungan produksi.