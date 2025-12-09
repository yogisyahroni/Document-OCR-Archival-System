# API Endpoints - Document OCR & Archival System

## Ikhtisar
Dokumen ini menjelaskan semua endpoint API yang tersedia dalam sistem Document OCR & Archival System, termasuk deskripsi, parameter, dan contoh penggunaan.

## Base URL
```
https://your-domain.com/api
```

## Autentikasi
Semua endpoint memerlukan autentikasi kecuali dinyatakan lain. Gunakan header:
```
Authorization: Bearer {jwt_token}
```

## Endpoint Dokumen

### 1. Upload Dokumen Baru
- **Endpoint**: `POST /documents`
- **Deskripsi**: Upload dokumen baru untuk diproses oleh sistem OCR
- **Autentikasi**: Diperlukan

#### Request Body
```json
{
  "file": "file_binary_data",
  "title": "Judul Dokumen",
  "category_id": 1
}
```

#### Response Success (202 Accepted)
```json
{
  "success": true,
  "message": "Document uploaded successfully. Processing in background.",
  "data": {
    "id": "uuid-v4-string",
    "title": "Judul Dokumen",
    "status": "PENDING",
    "uploaded_at": "2023-12-01T10:00:00Z",
    "uploaded_by_id": 1
  }
}
```

#### Response Error
```json
{
  "success": false,
  "error": "Error message",
  "code": "ERROR_CODE"
}
```

### 2. List Semua Dokumen
- **Endpoint**: `GET /documents`
- **Deskripsi**: Mendapatkan daftar dokumen dengan opsi filter dan pagination
- **Autentikasi**: Diperlukan

#### Query Parameters
- `page` (integer, default: 1) - Halaman hasil
- `size` (integer, default: 20, max: 100) - Jumlah item per halaman
- `status` (string) - Filter berdasarkan status (PENDING, PROCESSING, PROCESSED, FAILED)
- `category_id` (integer) - Filter berdasarkan kategori
- `date_from` (string, format: YYYY-MM-DD) - Filter dokumen dari tanggal
- `date_to` (string, format: YYYY-MM-DD) - Filter dokumen sampai tanggal
- `sort` (string) - Sortir hasil (contoh: "created_at:desc,title:asc")

#### Response Success
```json
{
  "success": true,
  "data": {
    "documents": [
      {
        "id": "uuid-v4-string",
        "title": "Judul Dokumen",
        "extracted_doc_number": "DOC-2023-001",
        "status": "PROCESSED",
        "s3_path": "documents/file.pdf",
        "uploaded_by_id": 1,
        "created_at": "2023-12-01T10:00:00Z",
        "ocr_metadata": {
          "confidence_score": 0.95,
          "engine_version": "tesseract-v5"
        }
      }
    ],
    "pagination": {
      "current_page": 1,
      "per_page": 20,
      "total": 100,
      "total_pages": 5
    }
  }
}
```

### 3. Detail Dokumen
- **Endpoint**: `GET /documents/{id}`
- **Deskripsi**: Mendapatkan detail lengkap dari dokumen tertentu
- **Autentikasi**: Diperlukan

#### Response Success
```json
{
  "success": true,
  "data": {
    "id": "uuid-v4-string",
    "title": "Judul Dokumen",
    "extracted_doc_number": "DOC-2023-001",
    "status": "PROCESSED",
    "s3_path": "documents/file.pdf",
    "full_text_indexed": true,
    "uploaded_by_id": 1,
    "created_at": "2023-12-01T10:00:00Z",
    "ocr_metadata": {
      "confidence_score": 0.95,
      "engine_version": "tesseract-v5",
      "processing_time": 45.2
    }
  }
}
```

### 4. Update Dokumen
- **Endpoint**: `PUT /documents/{id}`
- **Deskripsi**: Memperbarui informasi dokumen
- **Autentikasi**: Diperlukan

#### Request Body
```json
{
  "title": "Judul Dokumen Baru",
  "category_id": 2
}
```

#### Response Success
```json
{
  "success": true,
 "message": "Document updated successfully",
  "data": {
    "id": "uuid-v4-string",
    "title": "Judul Dokumen Baru",
    "category_id": 2
  }
}
```

### 5. Hapus Dokumen
- **Endpoint**: `DELETE /documents/{id}`
- **Deskripsi**: Menghapus dokumen dari sistem (termasuk file dari storage)
- **Autentikasi**: Diperlukan

#### Response Success
```json
{
  "success": true,
  "message": "Document deleted successfully"
}
```

## Endpoint Pencarian

### 1. Pencarian Dokumen
- **Endpoint**: `GET /search`
- **Deskripsi**: Mencari dokumen berdasarkan keyword atau nomor dokumen
- **Autentikasi**: Diperlukan

#### Query Parameters
- `q` (string) - Keyword pencarian teks penuh
- `document_number` (string) - Nomor dokumen spesifik
- `status` (string) - Filter berdasarkan status
- `date_from` (string, format: YYYY-MM-DD) - Filter dari tanggal
- `date_to` (string, format: YYYY-MM-DD) - Filter sampai tanggal
- `page` (integer, default: 1) - Halaman hasil
- `size` (integer, default: 20, max: 100) - Jumlah item per halaman
- `sort` (string) - Sortir hasil
- `highlight` (boolean, default: false) - Aktifkan highlight pada hasil

#### Response Success
```json
{
  "success": true,
  "data": {
    "total": 5,
    "documents": [
      {
        "id": "uuid-v4-string",
        "title": "Judul Dokumen",
        "extracted_doc_number": "DOC-2023-001",
        "status": "PROCESSED",
        "created_at": "2023-12-01T10:00:00Z",
        "score": 1.234,
        "highlight": {
          "title": ["<mark>Keyword</mark> in title"]
        }
      }
    ],
    "took": 15,
    "max_score": 1.234
  }
}
```

### 2. Saran Nomor Dokumen
- **Endpoint**: `GET /suggest`
- **Deskripsi**: Mendapatkan saran nomor dokumen berdasarkan input
- **Autentikasi**: Diperlukan

#### Query Parameters
- `q` (string) - Input untuk saran nomor dokumen

#### Response Success
```json
{
  "success": true,
  "data": {
    "query": "DOC-2023",
    "suggestions": [
      "DOC-2023-001",
      "DOC-2023-002",
      "DOC-2023-003"
    ]
 }
}
```

## Endpoint Kategori

### 1. List Kategori
- **Endpoint**: `GET /categories`
- **Deskripsi**: Mendapatkan daftar semua kategori dokumen
- **Autentikasi**: Diperlukan

#### Response Success
```json
{
  "success": true,
  "data": [
    {
      "id": 1,
      "name": "Kontrak"
    },
    {
      "id": 2,
      "name": "Invoice"
    },
    {
      "id": 3,
      "name": "Laporan"
    }
 ]
}
```

### 2. Tambah Kategori
- **Endpoint**: `POST /categories`
- **Deskripsi**: Menambah kategori baru
- **Autentikasi**: Diperlukan (admin)

#### Request Body
```json
{
  "name": "Kategori Baru"
}
```

#### Response Success
```json
{
  "success": true,
 "message": "Category created successfully",
  "data": {
    "id": 4,
    "name": "Kategori Baru"
  }
}
```

## Endpoint User

### 1. Register User
- **Endpoint**: `POST /auth/register`
- **Deskripsi**: Mendaftarkan user baru
- **Autentikasi**: Tidak diperlukan

#### Request Body
```json
{
  "email": "user@example.com",
  "password": "secure_password",
  "name": "Nama User"
}
```

#### Response Success
```json
{
  "success": true,
  "message": "User registered successfully",
  "data": {
    "user": {
      "id": 1,
      "email": "user@example.com",
      "name": "Nama User"
    },
    "token": "jwt_token_here"
  }
}
```

### 2. Login User
- **Endpoint**: `POST /auth/login`
- **Deskripsi**: Login user dan mendapatkan token JWT
- **Autentikasi**: Tidak diperlukan

#### Request Body
```json
{
  "email": "user@example.com",
  "password": "secure_password"
}
```

#### Response Success
```json
{
  "success": true,
  "message": "Login successful",
  "data": {
    "user": {
      "id": 1,
      "email": "user@example.com",
      "name": "Nama User"
    },
    "token": "jwt_token_here"
  }
}
```

### 3. Logout User
- **Endpoint**: `POST /auth/logout`
- **Deskripsi**: Logout user dan invalidasi token
- **Autentikasi**: Diperlukan

#### Response Success
```json
{
  "success": true,
  "message": "Logout successful"
}
```

### 4. Profile User
- **Endpoint**: `GET /auth/profile`
- **Deskripsi**: Mendapatkan informasi profil user saat ini
- **Autentikasi**: Diperlukan

#### Response Success
```json
{
  "success": true,
  "data": {
    "id": 1,
    "email": "user@example.com",
    "name": "Nama User",
    "created_at": "2023-12-01T10:00:00Z"
  }
}
```

## Endpoint Status dan Kesehatan

### 1. Health Check
- **Endpoint**: `GET /health`
- **Deskripsi**: Memeriksa status kesehatan sistem
- **Autentikasi**: Tidak diperlukan

#### Response Success
```json
{
  "success": true,
  "data": {
    "status": "healthy",
    "timestamp": "2023-12-01T10:00:00Z",
    "services": {
      "database": "connected",
      "redis": "connected",
      "elasticsearch": "connected",
      "s3": "connected"
    }
  }
}
```

### 2. Statistik Sistem
- **Endpoint**: `GET /stats`
- **Deskripsi**: Mendapatkan statistik sistem
- **Autentikasi**: Diperlukan (admin)

#### Response Success
```json
{
  "success": true,
  "data": {
    "total_documents": 1250,
    "processed_documents": 1200,
    "pending_documents": 30,
    "failed_documents": 20,
    "total_users": 50,
    "storage_used": "2.5 GB",
    "search_index_stats": {
      "docs_count": 1200,
      "store_size": "1.8 GB"
    }
 }
}
```

## Error Handling

### Kode Status HTTP
- `200 OK`: Permintaan berhasil
- `201 Created`: Resource berhasil dibuat
- `202 Accepted`: Permintaan diterima untuk diproses di background
- `400 Bad Request`: Permintaan tidak valid
- `401 Unauthorized`: Autentikasi gagal
- `403 Forbidden`: Akses ditolak
- `404 Not Found`: Resource tidak ditemukan
- `422 Unprocessable Entity`: Validasi data gagal
- `500 Internal Server Error`: Kesalahan server

### Format Error Response
```json
{
  "success": false,
  "error": "Error message",
  "code": "ERROR_CODE",
  "details": {
    "field": "error_detail"
  }
}
```

## Contoh Penggunaan

### Mengupload Dokumen
```bash
curl -X POST \
  https://your-domain.com/api/documents \
  -H 'Authorization: Bearer your_jwt_token' \
  -H 'Content-Type: multipart/form-data' \
 -F 'file=@document.pdf' \
  -F 'title=Invoice December 2023'
```

### Mencari Dokumen
```bash
curl -X GET \
  "https://your-domain.com/api/search?q=contract&document_number=DOC-2023-001" \
  -H 'Authorization: Bearer your_jwt_token'
```

### Mendapatkan Daftar Dokumen
```bash
curl -X GET \
  "https://your-domain.com/api/documents?page=1&size=10&status=PROCESSED" \
 -H 'Authorization: Bearer your_jwt_token'
```

## Kesimpulan

Endpoint-endpoint API ini menyediakan antarmuka komprehensif untuk berinteraksi dengan sistem Document OCR & Archival System. Semua endpoint dirancang untuk mengikuti prinsip REST dan menyediakan respons yang konsisten serta penanganan error yang baik.