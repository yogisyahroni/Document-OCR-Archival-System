# 📄 Technical Design Document (TDD): Document OCR & Archival System

## 1. Ikhtisar Sistem

Sistem ini dirancang sebagai solusi manajemen dokumen **terdistribusi** dan **asinkron** yang memastikan skalabilitas dan kinerja tinggi. Tujuan utamanya adalah menyimpan, mengindeks, dan mencari dokumen digital (terutama PDF) secara efisien berdasarkan metadata dan nomor dokumen yang diekstrak otomatis melalui OCR.

Fondasi aplikasi akan menggunakan **FrankenPHP** untuk *runtime* PHP yang efisien, dan arsitektur berbasis antrean (**Queue-based Architecture**) untuk menangani beban kerja berat OCR secara *background*.

---

## 2. Arsitektur dan Toolchain Teknis (Production Grade)

| Layer | Teknologi Kunci | Rationale (Best Practice) |
| :--- | :--- | :--- |
| **Aplikasi Web (API)** | **FrankenPHP + Symfony/Laravel** | Kinerja tinggi, dukungan *worker* proses, dan *framework* siap produksi. |
| **Web Server** | **Caddy (Built-in FrankenPHP)** | Pengganti Nginx/Apache yang modern, efisien, dan mendukung HTTP/3. |
| **Database Primer (Metadata)** | **PostgreSQL (v14+)** | *Reliable*, dukungan ACID *transaction*, dan kemampuan *indexing* yang kuat untuk metadata. |
| **Object Storage (File)** | **AWS S3 / Google Cloud Storage (GCS)** | **Wajib:** Penyimpanan *file* yang sangat terukur (horizontal), tahan lama, dan biaya-efektif. |
| **Antrean/Queue Broker** | **Redis** | Broker *in-memory* berkecepatan tinggi untuk *job* asinkron. |
| **OCR & Index Worker** | **Dedicated PHP Workers** | Proses *daemon* yang terpisah dari *web process* untuk memisahkan beban komputasi berat. |
| **Pencarian Teks Penuh** | **Elasticsearch / OpenSearch** | Mesin pencari terdistribusi untuk pencarian *full-text* dan *fuzzy matching* nomor dokumen yang cepat. |
| **Monitoring/Logging** | **Prometheus/Grafana + ELK Stack/Loki** | **Wajib:** Untuk *observability* (melacak *worker lag*, *request latency*, dan *debugging* *jobs*). |

---

## 3. Product Requirements Document (PRD) - Fitur Wajib

### Fungsionalitas Inti
* **Autentikasi & Otorisasi:** Sistem harus memiliki mekanisme *user login* dan *role-based access control* (RBAC) dasar.
* **Unggah Dokumen:** API *endpoint* yang menerima *file* (PDF) dan metadata awal.
* **Pemrosesan Asinkron:** Semua ekstraksi teks dan OCR harus dilakukan di *background* melalui *queue*.
* **Pencarian Cepat:** Mampu mencari dokumen berdasarkan *keyword*, tanggal, dan terutama **Nomor Dokumen** yang diekstrak.
* **Audit Trail:** Mencatat *user* yang mengunggah dan memodifikasi dokumen.

### Persyaratan Non-Fungsional (Wajib Produksi)
* **Skalabilitas:** Mampu menambah *worker* dan *search node* secara horizontal.
* **Reliability:** Penggunaan **Dead Letter Queue (DLQ)** untuk menangani *job* yang gagal dan *retry mechanism*.
* **Keamanan:** Dokumen tidak disimpan di *disk* server (hanya di *Object Storage*). Data sensitif harus dienkripsi saat *transit* (HTTPS/Caddy) dan saat *rest* (PostgreSQL/S3 encryption).

---

## 4. Alur Proses Unggah dan Ekstraksi Dokumen

1.  **Request:** Client mengirim `POST /api/documents`.
2.  **API Gateway (FrankenPHP):** Memvalidasi *request*. Menyimpan *file* ke S3/GCS.
3.  **Database Write:** Mencatat `document` di PostgreSQL dengan `status = PENDING`.
4.  **Queue Dispatch:** API mengirim *message* `DocumentIngestJob(document_id)` ke **Redis Queue**.
5.  **Response:** API mengembalikan `HTTP 202 Accepted` (pemrosesan sedang berlangsung).
6.  **Worker Consumption:** PHP Worker mengambil *job* dari antrean.
7.  **OCR Execution:** *Worker* mengunduh *file* dari S3 dan menjalankan OCR.
8.  **RegEx Extraction:** *Worker* menerapkan RegEx untuk mengidentifikasi `extracted_doc_number`.
9.  **Database Update:** *Worker* memperbarui `documents.status = PROCESSED` dan `documents.extracted_doc_number` di PostgreSQL.
10. **Index Write:** *Worker* mengirim *full-text* hasil OCR dan metadata ke **Elasticsearch**.


---

## 5. Entity Relationship Diagram (ERD) - Skema PostgreSQL

### 5.1. `documents` (Tabel Kunci)

| Kolom | Tipe Data | Constraint | Rasional |
| :--- | :--- | :--- | :--- |
| `id` | `UUID` | `PRIMARY KEY` | Menghindari *hotspot* ID serial dan memudahkan *sharding*. |
| `uploaded_by_id` | `BIGINT` | `NOT NULL`, `FOREIGN KEY` | Audit dan kepemilikan. |
| `s3_path` | `TEXT` | `NOT NULL`, `UNIQUE` | Lokasi *file* absolut di *object storage*. |
| `title` | `VARCHAR(255)` | `NOT NULL` | Metadata yang diinput pengguna. |
| `status` | `VARCHAR(20)` | `NOT NULL` | `PENDING`, `PROCESSING`, `PROCESSED`, `FAILED`. |
| `extracted_doc_number` | `VARCHAR(100)` | **INDEX** | Target utama pencarian cepat. |
| `full_text_indexed` | `BOOLEAN` | `NOT NULL` | Status sinkronisasi ke Elasticsearch. |
| `ocr_metadata` | `JSONB` | `NULLABLE` | Untuk *confidence scores* atau *engine version* (penting untuk *debugging*). |
| `created_at` | `TIMESTAMPTZ` | `NOT NULL` | |

### 5.2. `document_categories`

| Kolom | Tipe Data | Constraint |
| :--- | :--- | :--- |
| `id` | `INT` | `PRIMARY KEY` |
| `name` | `VARCHAR(100)` | `NOT NULL` |

### 5.3. `users` (Standar)

| Kolom | Tipe Data | Constraint |
| :--- | :--- | :--- |
| `id` | `BIGSERIAL` | `PRIMARY KEY` |
| `email` | `VARCHAR(255)` | `UNIQUE`, `NOT NULL` |
| `password_hash` | `VARCHAR(255)` | `NOT NULL` (Argon2) |