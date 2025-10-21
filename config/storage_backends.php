<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Storage Backend Configuration
    |--------------------------------------------------------------------------
    |
    | This file contains configuration options for the blob storage backends
    | supported by the Rekaz Simple Drive application.
    |
    */

    /*
    |--------------------------------------------------------------------------
    | Default Storage Backend
    |--------------------------------------------------------------------------
    |
    | This option controls the default storage backend that will be used
    | when no specific backend is requested.
    |
    */

    'default' => env('STORAGE_BACKEND', 'database'),

    /*
    |--------------------------------------------------------------------------
    | Maximum File Size
    |--------------------------------------------------------------------------
    |
    | Maximum file size in bytes that can be uploaded.
    | Default: 100MB (100 * 1024 * 1024)
    |
    */

    'max_file_size' => env('STORAGE_MAX_FILE_SIZE', 104857600),

    /*
    |--------------------------------------------------------------------------
    | Allowed MIME Types
    |--------------------------------------------------------------------------
    |
    | List of allowed MIME types for file uploads.
    | Leave empty to allow all MIME types.
    |
    */

    'allowed_mime_types' => [
        // Images
        'image/jpeg',
        'image/png',
        'image/gif',
        'image/webp',
        'image/svg+xml',
        
        // Documents
        'application/pdf',
        'application/msword',
        'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
        'application/vnd.ms-excel',
        'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
        'application/vnd.ms-powerpoint',
        'application/vnd.openxmlformats-officedocument.presentationml.presentation',
        
        // Text files
        'text/plain',
        'text/csv',
        'application/json',
        'application/xml',
        'text/xml',
        
        // Archives
        'application/zip',
        'application/x-rar-compressed',
        'application/x-7z-compressed',
        
        // Audio
        'audio/mpeg',
        'audio/wav',
        'audio/ogg',
        
        // Video
        'video/mp4',
        'video/mpeg',
        'video/quicktime',
        'video/webm',
    ],

    /*
    |--------------------------------------------------------------------------
    | Storage Backend Configurations
    |--------------------------------------------------------------------------
    |
    | Configuration templates for each storage backend type.
    | These are used for validation and documentation purposes.
    |
    */

    'backends' => [
        's3' => [
            'endpoint' => env('S3_ENDPOINT'),
            'bucket' => env('S3_BUCKET'),
            'access_key' => env('S3_ACCESS_KEY'),
            'secret_key' => env('S3_SECRET_KEY'),
            'region' => env('S3_REGION', 'us-east-1'),
            'use_path_style_endpoint' => env('S3_USE_PATH_STYLE_ENDPOINT', false),
            'prefix' => env('S3_PREFIX', 'blobs'),
            'required_fields' => [
                'access_key_id',
                'secret_access_key',
                'region',
                'bucket',
            ],
            'optional_fields' => [
                'endpoint',
                'use_path_style_endpoint',
                'prefix',
            ],
            'validation_rules' => [
                'access_key_id' => 'required|string|min:16|max:128',
                'secret_access_key' => 'required|string|min:16|max:128',
                'region' => 'required|string|min:2|max:50',
                'bucket' => 'required|string|min:3|max:63|regex:/^[a-z0-9][a-z0-9.-]*[a-z0-9]$/',
                'endpoint' => 'nullable|url',
                'use_path_style_endpoint' => 'nullable|boolean',
                'prefix' => 'nullable|string|max:255',
            ],
        ],
        
        'local' => [
            'storage_path' => env('LOCAL_STORAGE_PATH') ?: storage_path('app/blobs'),
            'create_directories' => env('LOCAL_CREATE_DIRECTORIES', true),
            'permissions' => env('LOCAL_PERMISSIONS', '755'),
            'required_fields' => [
                'path',
            ],
            'optional_fields' => [
                'create_directories',
                'permissions',
            ],
            'validation_rules' => [
                'path' => 'required|string|max:500',
                'create_directories' => 'nullable|boolean',
                'permissions' => 'nullable|string|regex:/^[0-7]{3,4}$/',
            ],
        ],
        
        'ftp' => [
            'host' => env('FTP_HOST'),
            'username' => env('FTP_USERNAME'),
            'password' => env('FTP_PASSWORD'),
            'port' => env('FTP_PORT', 21),
            'root' => env('FTP_ROOT', '/'),
            'passive' => env('FTP_PASSIVE', true),
            'ssl' => env('FTP_SSL', false),
            'timeout' => env('FTP_TIMEOUT', 30),
            'utf8' => env('FTP_UTF8', false),
            'prefix' => env('FTP_PREFIX', 'blobs'),
            'required_fields' => [
                'host',
                'username',
                'password',
            ],
            'optional_fields' => [
                'port',
                'root',
                'passive',
                'ssl',
                'timeout',
            ],
            'validation_rules' => [
                'host' => 'required|string|max:255',
                'username' => 'required|string|max:255',
                'password' => 'required|string|max:255',
                'port' => 'nullable|integer|min:1|max:65535',
                'root' => 'nullable|string|max:500',
                'passive' => 'nullable|boolean',
                'ssl' => 'nullable|boolean',
                'timeout' => 'nullable|integer|min:1|max:300',
            ],
        ],
        
        'database' => [
            'compression' => env('DATABASE_COMPRESSION', false),
            'chunk_size' => env('DATABASE_CHUNK_SIZE', 1048576), // 1MB
            'required_fields' => [],
            'optional_fields' => [
                'compression',
                'chunk_size',
            ],
            'validation_rules' => [
                'compression' => 'nullable|boolean',
                'chunk_size' => 'nullable|integer|min:1024|max:16777216', // 1KB to 16MB
            ],
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Storage Backend Priorities
    |--------------------------------------------------------------------------
    |
    | Priority order for storage backends when auto-selecting.
    | Higher numbers indicate higher priority.
    |
    */

    'backend_priorities' => [
        's3' => 100,
        'local' => 80,
        'ftp' => 60,
        'database' => 40,
    ],

    /*
    |--------------------------------------------------------------------------
    | Blob Path Configuration
    |--------------------------------------------------------------------------
    |
    | Configuration for how blob paths are generated and organized.
    |
    */

    'path' => [
        'use_subdirectories' => env('STORAGE_USE_SUBDIRECTORIES', true),
        'subdirectory_depth' => env('STORAGE_SUBDIRECTORY_DEPTH', 2),
        'subdirectory_length' => env('STORAGE_SUBDIRECTORY_LENGTH', 2),
    ],

    /*
    |--------------------------------------------------------------------------
    | Cleanup Configuration
    |--------------------------------------------------------------------------
    |
    | Configuration for automatic cleanup of orphaned blobs and storage.
    |
    */

    'cleanup' => [
        'enabled' => env('STORAGE_CLEANUP_ENABLED', true),
        'orphaned_blob_retention_days' => env('STORAGE_ORPHANED_RETENTION_DAYS', 7),
        'failed_upload_retention_hours' => env('STORAGE_FAILED_UPLOAD_RETENTION_HOURS', 24),
    ],

    /*
    |--------------------------------------------------------------------------
    | Performance Configuration
    |--------------------------------------------------------------------------
    |
    | Configuration options for performance optimization.
    |
    */

    'performance' => [
        'enable_deduplication' => env('STORAGE_ENABLE_DEDUPLICATION', true),
        'cache_metadata' => env('STORAGE_CACHE_METADATA', true),
        'cache_ttl' => env('STORAGE_CACHE_TTL', 3600), // 1 hour
    ],
];