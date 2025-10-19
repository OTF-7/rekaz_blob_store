# Simple Drive - Object Storage API

> A Laravel-based object storage system with multiple backend support

## Overview

This is a RESTful API that provides unified blob storage across multiple backends: Database, Local File System, S3-compatible storage, and FTP. The system uses Bearer token authentication and supports Base64-encoded binary data.

## Key Features

- **Multiple Storage Backends**: Database, Local, S3-compatible (HTTP-only), FTP
- **RESTful API**: `/v1/blobs` endpoints for CRUD operations
- **Authentication**: Laravel Sanctum with Bearer tokens
- **Base64 Support**: Strict validation with decode verification
- **S3 HTTP Implementation**: Manual AWS Signature V4 (no AWS SDK)
- **Comprehensive Testing**: 29+ automated tests
- **API Documentation**: Interactive Swagger UI at `/api/documentation`

## Architecture

### Controllers (`app/Http/Controllers/`)
- `AuthController`: Handles user registration/login
- `BlobController`: Manages blob storage/retrieval operations
- `UserController`: User profile management

### Storage System (`app/Services/Storage/`)
- `StorageManager`: Coordinates storage operations
- `DatabaseStorageDriver`: Stores blobs in database tables
- `LocalStorageDriver`: Stores blobs as local files
- `S3StorageDriver`: HTTP-only S3-compatible storage
- `FtpStorageDriver`: FTP server storage

### Database Design
- `blobs` table: Metadata (ID, size, MIME type, storage backend)
- `blob_data` table: Actual data (database backend only)
- `users` table: User authentication

## API Endpoints

### Authentication
```bash
# Register
POST /v1/auth/register
{
  "name": "John Doe",
  "email": "john@example.com",
  "password": "SecurePass123!",
  "password_confirmation": "SecurePass123!"
}

# Login
POST /v1/auth/login
{
  "email": "john@example.com",
  "password": "SecurePass123!"
}
```

### Blob Operations
```bash
# Store blob
POST /v1/blobs
Authorization: Bearer {token}
{
  "id": "unique-blob-id",
  "data": "SGVsbG8gV29ybGQh"  // Base64 encoded
}

# Retrieve blob
GET /v1/blobs/{id}
Authorization: Bearer {token}

# List blobs
GET /v1/blobs
Authorization: Bearer {token}
```

## Configuration

### Storage Backend Selection
```env
# Options: database, local, s3, ftp
STORAGE_BACKEND=database
```

### S3 Configuration
```env
STORAGE_BACKEND=s3
AWS_ACCESS_KEY_ID=your_key
AWS_SECRET_ACCESS_KEY=your_secret
AWS_DEFAULT_REGION=us-east-1
AWS_BUCKET=your-bucket
AWS_ENDPOINT=https://s3.amazonaws.com
```

### FTP Configuration
```env
STORAGE_BACKEND=ftp
FTP_HOST=ftp.example.com
FTP_USERNAME=username
FTP_PASSWORD=password
FTP_PORT=21
FTP_ROOT=/path/on/server
FTP_PASSIVE=true
```

## Quick Start

```bash
# Install dependencies
composer install

# Setup environment
cp .env.example .env
php artisan key:generate

# Run migrations
php artisan migrate

# Start server
php artisan serve

# Run tests
php artisan test

# View API docs
# Visit: http://localhost:8000/api/documentation
```

## Technical Highlights

### S3 HTTP-Only Implementation
Implemented AWS Signature Version 4 authentication manually using Laravel's HTTP client, avoiding AWS SDK dependency as required.

### Storage Driver Pattern
All storage backends implement `StorageDriverInterface`, making the system easily extensible and testable.

### Security Features
- Bearer token authentication
- Rate limiting on auth endpoints
- Comprehensive input validation
- Base64 decode verification
- SQL injection protection via Laravel ORM

### Testing Strategy
- Unit tests for individual components
- Feature tests for complete API workflows
- Storage backend integration tests
- Authentication and authorization tests

## Troubleshooting

### SQLite Database Path Error

If you encounter the error `Database file at path does not exist` during deployment or migration:

**Problem**: Laravel cannot find the SQLite database file, especially on production servers.

**Solution**:
1. **Use absolute paths in production `.env`**:
   ```env
   DB_CONNECTION=sqlite
   DB_DATABASE=/full/path/to/your/project/database/database.sqlite
   ```

2. **Create the database file**:
   ```bash
   touch database/database.sqlite
   chmod 664 database/database.sqlite
   ```

3. **Clear configuration cache**:
   ```bash
   php artisan config:clear
   php artisan migrate
   ```

4. **Set proper permissions**:
   ```bash
   chown www-data:www-data database/database.sqlite
   chmod 664 database/database.sqlite
   ```

**Note**: Relative paths in `DB_DATABASE` may not work correctly on all server environments. Always use absolute paths for production deployments.

### SQLite Driver Missing Error

If you encounter the error `could not find driver (Connection: sqlite, SQL: PRAGMA foreign_keys = ON;)`:

**Problem**: The PHP SQLite extension is not installed or enabled on your server.

**Solution - Install php-sqlite3 extension**:

**Ubuntu/Debian**:
```bash
sudo apt update
sudo apt install php-sqlite3
sudo systemctl restart apache2  # or nginx
```

**CentOS/RHEL/Rocky Linux**:
```bash
sudo yum install php-pdo php-sqlite3
# or for newer versions:
sudo dnf install php-pdo php-sqlite3
sudo systemctl restart httpd  # or nginx
```

**macOS (Homebrew)**:
```bash
brew install php
# SQLite support is usually included by default
```

**Windows (XAMPP/WAMP)**:
- Uncomment `;extension=pdo_sqlite` in `php.ini`
- Uncomment `;extension=sqlite3` in `php.ini`
- Restart Apache

**Verify installation**:
```bash
php -m | grep sqlite
# Should show: pdo_sqlite, sqlite3
```

**Alternative - Use MySQL instead**:
If SQLite installation is not possible, update your `.env`:
```env
DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=your_database
DB_USERNAME=your_username
DB_PASSWORD=your_password
```

## Project Structure
```
app/
├── Http/Controllers/     # Request handlers
├── Models/              # Database models (User, Blob, BlobStorage)
├── Services/Storage/    # Storage backend implementations
└── Exceptions/          # Custom error handling

database/
├── migrations/          # Database schema
└── factories/           # Test data generation

tests/
├── Feature/            # End-to-end API tests
└── Unit/               # Component tests

routes/api.php          # API endpoint definitions
```
