# Simple Drive - Object Storage API

> A Laravel-based object storage system with multiple backend support

## Overview

This is a RESTful API that provides unified blob storage across multiple backends: Database, Local File System, S3-compatible storage, and FTP. The system uses Bearer token authentication and supports Base64-encoded binary data.

## Requirements

Before installing this project, ensure you have the following:

### System Requirements
- **PHP 8.1+** with the following extensions:
  - `pdo_sqlite` (for SQLite database support)
  - `sqlite3` (SQLite extension)
  - `curl` (for HTTP requests)
  - `json` (JSON processing)
  - `mbstring` (multibyte string support)
- **Composer** (PHP dependency manager)
- **Web server** (Apache, Nginx, or PHP built-in server)

### Optional Requirements (for specific storage backends)
- **S3-compatible storage** credentials (AWS S3, MinIO, etc.)
- **FTP server** access (for FTP storage backend)
- **MySQL/PostgreSQL** (if not using SQLite)

### Verify PHP Extensions
Check if required extensions are installed:
```bash
php -m | grep -E "(pdo_sqlite|sqlite3|curl|json|mbstring)"
```

If any extensions are missing, install them:

**Ubuntu/Debian:**
```bash
sudo apt update
sudo apt install php-sqlite3 php-curl php-json php-mbstring
```

**CentOS/RHEL:**
```bash
sudo yum install php-pdo php-sqlite3 php-curl php-json php-mbstring
```

**macOS (Homebrew):**
```bash
brew install php
# Extensions are usually included by default
```

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
- `AuthController`: Handles user registration/login/logout and user profile
- `BlobController`: Manages blob storage/retrieval operations and statistics

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

# Logout
POST /v1/auth/logout
Authorization: Bearer {token}

# Get User Profile
GET /v1/user/profile
Authorization: Bearer {token}
```

### Blob Management
```bash
# Store blob
POST /v1/blobs
Authorization: Bearer {token}
{
  "id": "unique-blob-id",
  "data": "SGVsbG8gV29ybGQh"  // Base64 encoded
}

# List blobs (with pagination and filtering)
GET /v1/blobs?page=1&per_page=20&mime_type=image/
Authorization: Bearer {token}

# Retrieve blob content
GET /v1/blobs/{id}
Authorization: Bearer {token}

# Retrieve blob metadata only
GET /v1/blobs/{id}?metadata_only=1
Authorization: Bearer {token}

# Download blob as attachment
GET /v1/blobs/{id}?download=1
Authorization: Bearer {token}

# Get storage statistics
GET /v1/blobs/stats
Authorization: Bearer {token}

# Delete blob
DELETE /v1/blobs/{id}
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

## Production Deployment

### Storage Backend Configuration in Production

If your storage backend always defaults to database in production despite configuring a different backend in `.env`, follow these troubleshooting steps:

#### 1. Diagnose Storage Configuration

Use the built-in diagnostic command to check your storage configuration:

```bash
# Check all storage backends
php artisan storage:diagnose

# Check specific backend (e.g., ftp)
php artisan storage:diagnose --backend=ftp
```

This command will show:
- Current environment variables
- Configuration cache status
- Backend availability and connectivity
- Missing environment variables

#### 2. Clear Laravel Configuration Cache

**Most Common Issue**: Laravel caches configuration in production, ignoring `.env` changes.

```bash
# Clear all caches
php artisan config:clear
php artisan cache:clear
php artisan route:clear
php artisan view:clear

# Rebuild optimized configuration
php artisan config:cache
```

#### 3. Verify Environment Variables

Ensure your production `.env` file contains the correct storage backend configuration:

```env
# For FTP backend
STORAGE_BACKEND=ftp
FTP_HOST=your-ftp-server.com
FTP_USERNAME=your-username
FTP_PASSWORD=your-password
FTP_PORT=21
FTP_ROOT=/path/to/storage
FTP_PASSIVE=true
FTP_SSL=false

# For S3 backend
STORAGE_BACKEND=s3
S3_ENDPOINT=https://s3.amazonaws.com
S3_BUCKET=your-bucket-name
S3_ACCESS_KEY=your-access-key
S3_SECRET_KEY=your-secret-key
S3_REGION=us-east-1

# For local file storage
STORAGE_BACKEND=local
LOCAL_STORAGE_PATH=storage/app/blobs
```

#### 4. Check File Permissions

Ensure your web server can read the `.env` file:

```bash
# Set proper permissions
chmod 644 .env
chown www-data:www-data .env  # or your web server user
```

#### 5. Test Backend Connectivity

After clearing caches, test your storage backend:

```bash
# Test specific backend
php artisan storage:diagnose --backend=your_backend

# Or test via tinker
php artisan tinker
>>> $manager = app('App\Services\StorageManager');
>>> $manager->getCurrentBackend();
>>> $manager->testDriver('ftp'); // or your backend
```

#### 6. Common Production Issues

**Issue**: Backend falls back to database despite configuration
**Cause**: Backend configuration is invalid or connectivity fails
**Solution**: 
- Check network connectivity to FTP/S3 servers
- Verify credentials are correct
- Ensure firewall allows outbound connections
- Check server logs for connection errors

**Issue**: Environment variables not loading
**Cause**: `.env` file not readable or cached config outdated
**Solution**:
- Verify `.env` file exists and has correct permissions
- Clear configuration cache: `php artisan config:clear`
- Restart web server/PHP-FPM

**Issue**: Storage backend works locally but not in production
**Cause**: Different network environment or missing extensions
**Solution**:
- Install required PHP extensions (curl, ftp, etc.)
- Check production server network access
- Verify production credentials differ from local

#### 7. Deployment Checklist

```bash
# 1. Upload files and install dependencies
composer install --no-dev --optimize-autoloader

# 2. Configure environment
cp .env.example .env
# Edit .env with production values

# 3. Generate application key
php artisan key:generate

# 4. Run migrations
php artisan migrate --force

# 5. Clear and cache configuration
php artisan config:clear
php artisan config:cache
php artisan route:cache
php artisan view:cache

# 6. Set permissions
chmod -R 755 storage bootstrap/cache
chown -R www-data:www-data storage bootstrap/cache

# 7. Test storage configuration
php artisan storage:diagnose
```

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
