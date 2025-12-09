# Architecture Decision Record (ADR) - Document OCR & Archival System

## ADR-001: Pemilihan Teknologi Backend - FrankenPHP + Symfony/Laravel

**Tanggal**: 2025-12-09  
**Status**: Accepted  
**Penulis**: Roo

### Konteks
Sistem membutuhkan backend yang mampu menangani beban kerja berat OCR secara efisien dengan kinerja tinggi dan dukungan proses worker. Kami perlu memilih antara berbagai teknologi backend PHP yang tersedia.

### Keputusan
Menggunakan FrankenPHP sebagai runtime PHP dengan framework Symfony atau Laravel. FrankenPHP menawarkan kinerja tinggi dengan model worker yang efisien, cocok untuk beban kerja OCR yang intensif.

### Konsekuensi
- **Positif**: Kinerja tinggi, dukungan worker proses, ekosistem PHP yang kuat
- **Negatif**: Kurva belajar untuk tim yang belum familiar dengan FrankenPHP, potensi masalah kompatibilitas dengan beberapa library PHP

---

## ADR-002: Pemilihan Database - PostgreSQL

**Tanggal**: 2025-12-09  
**Status**: Accepted  
**Penulis**: Roo

### Konteks
Sistem membutuhkan database yang dapat diandalkan untuk menyimpan metadata dokumen dengan dukungan transaksi ACID dan kemampuan indexing yang kuat untuk pencarian cepat.

### Keputusan
Menggunakan PostgreSQL versi 14+ karena keandalan, dukungan ACID transaction, dan kemampuan indexing yang kuat untuk metadata dokumen.

### Konsekuensi
- **Positif**: Dukungan ACID, kemampuan indexing lanjutan, fitur JSONB untuk metadata fleksibel
- **Negatif**: Kurva belajar lebih tinggi dibanding MySQL untuk tim yang belum familiar

---

## ADR-003: Pemrosesan Asinkron - Redis Queue

**Tanggal**: 2025-12-09  
**Status**: Accepted  
**Penulis**: Roo

### Konteks
OCR dan pemrosesan dokumen membutuhkan waktu yang lama dan tidak boleh memblokir thread utama. Kami perlu sistem antrean yang dapat menangani beban kerja berat secara asinkron.

### Keputusan
Menggunakan Redis sebagai broker antrean untuk job asinkron karena kinerja tinggi dan kemampuan untuk menangani beban kerja berat OCR secara efisien.

### Konsekuensi
- **Positif**: Kinerja tinggi, dukungan untuk Dead Letter Queue, skalabilitas horizontal
- **Negatif**: Ketergantungan tambahan, kebutuhan untuk konfigurasi replikasi untuk ketersediaan tinggi

---

## ADR-004: Pencarian Teks Penuh - Elasticsearch

**Tanggal**: 2025-12-09  
**Status**: Accepted  
**Penulis**: Roo

### Konteks
Sistem membutuhkan kemampuan pencarian teks penuh dan fuzzy matching untuk nomor dokumen yang cepat dan akurat.

### Keputusan
Menggunakan Elasticsearch sebagai mesin pencarian terdistribusi untuk pencarian teks penuh dan nomor dokumen.

### Konsekuensi
- **Positif**: Pencarian teks penuh yang cepat, kemampuan fuzzy matching, skalabilitas horizontal
- **Negatif**: Ketergantungan tambahan, kebutuhan untuk manajemen skema dan mapping

---

## ADR-005: Penyimpanan File - S3 Compatible Storage

**Tanggal**: 2025-12-09  
**Status**: Accepted  
**Penulis**: Roo

### Konteks
Sistem membutuhkan penyimpanan file dokumen yang skalabel dan andal, bukan disimpan di disk server lokal.

### Keputusan
Menggunakan penyimpanan S3-compatible (AWS S3 atau Google Cloud Storage) untuk menyimpan dokumen digital. Ini memungkinkan skalabilitas horizontal dan ketersediaan tinggi.

### Konsekuensi
- **Positif**: Skalabilitas horizontal, ketersediaan tinggi, biaya efisien untuk penyimpanan besar
- **Negatif**: Ketergantungan pada layanan eksternal, kebutuhan bandwidth untuk mengakses file

---

## ADR-006: Autentikasi & Otorisasi - JWT dengan Refresh Token

**Tanggal**: 2025-12-09  
**Status**: Accepted  
**Penulis**: Roo

### Konteks
Sistem membutuhkan mekanisme autentikasi yang aman dan dapat diskalakan untuk mengelola akses pengguna ke dokumen mereka.

### Keputusan
Menggunakan JWT (JSON Web Tokens) dengan refresh token untuk manajemen sesi. Ini menyediakan stateless authentication yang cocok untuk arsitektur terdistribusi.

### Konsekuensi
- **Positif**: Stateless authentication, cocok untuk microservices, aman jika diimplementasikan dengan benar
- **Negatif**: Kebutuhan untuk manajemen refresh token, potensi masalah dengan revocation

---

## ADR-007: Monitoring & Observability - Prometheus + Grafana + Loki

**Tanggal**: 2025-12-09  
**Status**: Accepted  
**Penulis**: Roo

### Konteks
Sistem membutuhkan monitoring dan logging yang komprehensif untuk memastikan ketersediaan dan kinerja tinggi.

### Keputusan
Menggunakan stack Prometheus untuk metrics, Grafana untuk visualisasi, dan Loki untuk centralized logging. Ini menyediakan solusi observability yang lengkap dan dapat diskalakan.

### Konsekuensi
- **Positif**: Solusi observability yang lengkap, dapat diskalakan, integrasi yang baik
- **Negatif**: Ketergantungan tambahan, kebutuhan untuk mengelola infrastruktur monitoring