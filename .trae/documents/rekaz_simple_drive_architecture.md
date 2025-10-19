# Rekaz Simple Drive - Technical Architecture Document

## 1. Architecture Design

```mermaid
graph TD
    A[API Client] --> B[Laravel Application]
    B --> C[Authentication Middleware]
    C --> D[Blob Controller]
    D --> E[Storage Manager]
    E --> F[S3 Storage Driver]
    E --> G[Database Storage Driver]
    E --> H[Local Storage Driver]
    E --> I[FTP Storage Driver]
    F --> J[S3 Compatible Service]
    G --> K[Storage Database]
    H --> L[Local File System]
    I --> M[FTP Server]
    D --> N[Blob Metadata Repository]
    N --> O[Application Database]

    subgraph "Laravel Application Layer"
        B
        C
        D
        E
        N
    end

    subgraph "Storage Backends"
        F
        G
        H
        I
    end

    subgraph "External Services"
        J
        M
    end

    subgraph "Data Layer"
        K
        O
        L
    end
```

## 2. Technology Description

- Frontend: None (API-only service)
- Backend: Laravel 10.x + PHP 8.1+
- Database: MySQL 8.0 / PostgreSQL 14+
- Documentation: Laravel Swagger (L5-Swagger)
- Testing: PHPUnit + Laravel Testing Framework
- HTTP Client: Guzzle HTTP (built into Laravel)
- FTP Client: league/flysystem-ftp
- Authentication: Laravel Sanctum (API tokens)

## 3. Route Definitions

| Route | Purpose |
|-------|---------|
| POST /v1/blobs | Store a new blob with base64 encoded data |
| GET /v1/blobs/{id} | Retrieve a blob by its unique identifier |
| GET /api/documentation | Swagger API documentation interface |
| POST /api/auth/tokens | Generate authentication tokens (admin) |

## 4. API Definitions

### 4.1 Core API

**Blob Storage**
```
POST /v1/blobs
```

Request:
| Param Name | Param Type | isRequired | Description |
|------------|------------|------------|-------------|
| id | string | true | Unique identifier for the blob (UUID, path, or random string) |
| data | string | true | Base64 encoded binary data |

Response:
| Param Name | Param Type | Description |
|------------|------------|-------------|
| success | boolean | Operation success status |
| message | string | Success or error message |
| id | string | The stored blob identifier |

Example Request:
```json
{
  "id": "user-avatar-123e4567-e89b-12d3-a456-426614174000",
  "data": "SGVsbG8gU2ltcGxlIFN0b3JhZ2UgV29ybGQh"
}
```

Example Response:
```json
{
  "success": true,
  "message": "Blob stored successfully",
  "id": "user-avatar-123e4567-e89b-12d3-a456-426614174000"
}
```

**Blob Retrieval**
```
GET /v1/blobs/{id}
```

Path Parameters:
| Param Name | Param Type | isRequired | Description |
|------------|------------|------------|-------------|
| id | string | true | Unique identifier of the blob to retrieve |

Response:
| Param Name | Param Type | Description |
|------------|------------|-------------|
| id | string | The blob identifier |
| data | string | Base64 encoded binary data |
| size | integer | Size of the blob in bytes |
| created_at | string | ISO 8601 UTC timestamp |

Example Response:
```json
{
  "id": "user-avatar-123e4567-e89b-12d3-a456-426614174000",
  "data": "SGVsbG8gU2ltcGxlIFN0b3JhZ2UgV29ybGQh",
  "size": 27,
  "created_at": "2023-01-22T21:37:55Z"
}
```

**Authentication**
```
Authorization: Bearer {token}
```

All API endpoints require Bearer token authentication in the request headers.

## 5. Server Architecture Diagram

```mermaid
graph TD
    A[HTTP Request] --> B[Laravel Router]
    B --> C[Authentication Middleware]
    C --> D[BlobController]
    D --> E[BlobService]
    E --> F[StorageManager]
    F --> G[Storage Driver Interface]
    G --> H[Concrete Storage Drivers]
    E --> I[BlobRepository]
    I --> J[Eloquent Model]
    J --> K[Database]

    subgraph "Controller Layer"
        D
    end

    subgraph "Service Layer"
        E
        F
    end

    subgraph "Repository Layer"
        I
        J
    end

    subgraph "Storage Abstraction"
        G
        H
    end
```

## 6. Data Model

### 6.1 Data Model Definition

```mermaid
erDiagram
    BLOBS ||--o{ BLOB_DATA : stores
    USERS ||--o{ API_TOKENS : has
    API_TOKENS ||--o{ BLOBS : creates

    BLOBS {
        string id PK
        string original_filename
        integer size_bytes
        string mime_type
        string storage_backend
        string storage_path
        string checksum_md5
        timestamp created_at
        timestamp updated_at
    }

    BLOB_DATA {
        bigint id PK
        string blob_id FK
        longblob data
        timestamp created_at
        timestamp updated_at
    }

    USERS {
        bigint id PK
        string name
        string email
        timestamp created_at
        timestamp updated_at
    }

    API_TOKENS {
        bigint id PK
        bigint user_id FK
        string name
        string token
        text abilities
        timestamp last_used_at
        timestamp expires_at
        timestamp created_at
        timestamp updated_at
    }
```

### 6.2 Data Definition Language

**Blob Metadata Table (blobs)**
```sql
-- Create blobs table for metadata tracking
CREATE TABLE blobs (
    id VARCHAR(255) PRIMARY KEY,
    original_filename VARCHAR(255),
    size_bytes BIGINT UNSIGNED NOT NULL,
    mime_type VARCHAR(100),
    storage_backend ENUM('s3', 'database', 'local', 'ftp') NOT NULL,
    storage_path TEXT,
    checksum_md5 VARCHAR(32),
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
);

-- Create indexes for performance
CREATE INDEX idx_blobs_storage_backend ON blobs(storage_backend);
CREATE INDEX idx_blobs_created_at ON blobs(created_at DESC);
CREATE INDEX idx_blobs_size ON blobs(size_bytes);
```

**Blob Data Table (blob_data) - For database backend**
```sql
-- Create blob_data table for database storage backend
CREATE TABLE blob_data (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    blob_id VARCHAR(255) NOT NULL,
    data LONGBLOB NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (blob_id) REFERENCES blobs(id) ON DELETE CASCADE
);

-- Create index for blob_id lookups
CREATE UNIQUE INDEX idx_blob_data_blob_id ON blob_data(blob_id);
```

**Users Table (users)**
```sql
-- Create users table for API token management
CREATE TABLE users (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(255) NOT NULL,
    email VARCHAR(255) UNIQUE NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
);
```

**API Tokens Table (personal_access_tokens) - Laravel Sanctum**
```sql
-- Create personal_access_tokens table for Laravel Sanctum
CREATE TABLE personal_access_tokens (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    tokenable_type VARCHAR(255) NOT NULL,
    tokenable_id BIGINT UNSIGNED NOT NULL,
    name VARCHAR(255) NOT NULL,
    token VARCHAR(64) UNIQUE NOT NULL,
    abilities TEXT,
    last_used_at TIMESTAMP NULL,
    expires_at TIMESTAMP NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
);

-- Create indexes
CREATE INDEX idx_personal_access_tokens_tokenable ON personal_access_tokens(tokenable_type, tokenable_id);
CREATE UNIQUE INDEX idx_personal_access_tokens_token ON personal_access_tokens(token);
```

**Initial Data**
```sql
-- Insert default admin user
INSERT INTO users (name, email) VALUES 
('System Administrator', 'admin@rekaz.com');

-- Configuration table for storage backends
CREATE TABLE storage_configurations (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    backend_type ENUM('s3', 'database', 'local', 'ftp') NOT NULL,
    is_active BOOLEAN DEFAULT FALSE,
    configuration JSON NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
);

-- Insert default local storage configuration
INSERT INTO storage_configurations (backend_type, is_active, configuration) VALUES 
('local', TRUE, '{"storage_path": "/var/www/storage/blobs"}');
```

## 7. Storage Backend Implementation

### 7.1 Storage Driver Interface

```php
interface StorageDriverInterface
{
    public function store(string $id, string $data): bool;
    public function retrieve(string $id): ?string;
    public function delete(string $id): bool;
    public function exists(string $id): bool;
}
```

### 7.2 Configuration Structure

**S3 Compatible Storage Configuration:**
```json
{
  "endpoint": "https://s3.amazonaws.com",
  "bucket": "rekaz-simple-drive",
  "region": "us-east-1",
  "access_key": "AKIAIOSFODNN7EXAMPLE",
  "secret_key": "wJalrXUtnFEMI/K7MDENG/bPxRfiCYEXAMPLEKEY"
}
```

**FTP Configuration:**
```json
{
  "host": "ftp.example.com",
  "port": 21,
  "username": "ftpuser",
  "password": "ftppass",
  "directory": "/blobs",
  "passive": true
}
```

**Local Storage Configuration:**
```json
{
  "storage_path": "/var/www/storage/blobs",
  "permissions": "0755"
}
```