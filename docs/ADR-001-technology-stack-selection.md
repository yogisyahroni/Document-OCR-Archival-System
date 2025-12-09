# ADR-001: Pemilihan Teknologi dan Arsitektur Sistem

## Status
Diterima

## Konteks
Sistem Document OCR & Archival System membutuhkan arsitektur yang skalabel, handal, dan mampu menangani beban kerja berat OCR secara asinkron. Tim pengembang harus membuat keputusan arsitektur penting mengenai teknologi yang akan digunakan untuk setiap layer sistem.

## Keputusan
Kami memutuskan untuk menggunakan kombinasi teknologi berikut:

### Backend
- **Runtime**: FrankenPHP untuk kinerja tinggi dan efisiensi memori
- **Framework**: PHP native dengan struktur modular (bukan framework penuh untuk kontrol penuh)
- **Web Server**: Caddy (dengan integrasi FrankenPHP) untuk kemudahan konfigurasi dan dukungan HTTP/3

### Database
- **Database Primer**: PostgreSQL v14+ untuk kebutuhan transaksi ACID dan indexing yang kuat
- **Object Storage**: AWS S3 / Google Cloud Storage untuk menyimpan file dokumen secara skalabel
- **Queue Broker**: Redis untuk manajemen background jobs
- **Search Engine**: Elasticsearch / OpenSearch untuk pencarian teks penuh

### Arsitektur
- **Pendekatan**: Queue-based architecture untuk pemrosesan OCR asinkron
- **Worker**: PHP workers terdedikasi untuk memisahkan beban komputasi berat dari proses web
- **Deployment**: Containerization dengan Docker dan Docker Compose

## Konsekuensi

### Positif
- **Kinerja tinggi**: FrankenPHP memberikan kinerja mendekati C++/Go sambil tetap menggunakan PHP
- **Skalabilitas**: Arsitektur berbasis queue memungkinkan scaling horizontal untuk worker
- **Kemudahan pengelolaan**: Docker memudahkan deployment dan pengelolaan lingkungan
- **Kemampuan pencarian**: Elasticsearch menyediakan pencarian teks penuh yang kuat
- **Keandalan**: Redis sebagai queue broker menyediakan keandalan tinggi untuk background jobs

### Negatif
- **Kompleksitas**: Arsitektur terdistribusi lebih kompleks daripada monolith tradisional
- **Learning curve**: Tim perlu memahami teknologi baru seperti FrankenPHP dan Elasticsearch
- **Biaya infrastruktur**: Kebutuhan untuk beberapa layanan (PostgreSQL, Redis, Elasticsearch, S3) meningkatkan biaya infrastruktur
- **Operational overhead**: Membutuhkan pengelolaan beberapa layanan yang berjalan secara bersamaan

## Alasan Alternatif yang Dipertimbangkan

### Runtime Alternatif
- **Node.js**: Performa baik, tetapi kurang cocok untuk operasi CPU-intensive seperti OCR
- **Go**: Performa sangat baik, tetapi memerlukan waktu pembelajaran lebih lama untuk tim PHP
- **Traditional PHP-FPM**: Lebih familiar tetapi performa dan efisiensi memori lebih rendah dari FrankenPHP

### Database Alternatif
- **MySQL**: Familiar, tetapi PostgreSQL memiliki fitur indexing dan JSON handling yang lebih baik
- **MongoDB**: Schema flexibility, tetapi kurang cocok untuk transaksi ACID yang dibutuhkan

### Queue Alternatif
- **RabbitMQ**: Lebih mature, tetapi Redis lebih ringan dan lebih mudah dioperasikan
- **Apache Kafka**: Lebih powerful, tetapi terlalu kompleks untuk kasus penggunaan ini

### Search Alternatif
- **Solr**: Alternatif kuat, tetapi Elasticsearch lebih familiar dan memiliki ekosistem lebih luas
- **Database full-text search**: Kurang powerful dibandingkan solusi search engine khusus