<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasOne;

/**
 * Blob Model - Represents blob metadata in the system
 * 
 * @property string $id Custom string identifier (primary key)
 * @property int $size_bytes Size of the blob in bytes
 * @property string|null $mime_type MIME type of the blob
 * @property string $storage_backend Storage backend used (s3, database, local, ftp)
 * @property string|null $storage_path Path where blob is stored in the backend
 * @property string|null $checksum_md5 MD5 checksum of the blob data
 */
class Blob extends Model
{
    use HasFactory;

    /**
     * The primary key for the model.
     */
    protected $primaryKey = 'id';

    /**
     * The "type" of the auto-incrementing ID.
     */
    protected $keyType = 'string';

    /**
     * Indicates if the IDs are auto-incrementing.
     */
    public $incrementing = false;

    /**
     * The attributes that are mass assignable.
     */
    protected $fillable = [
        'id',
        'size_bytes',
        'mime_type',
        'storage_backend',
        'storage_path',
        'checksum_md5',
    ];

    /**
     * The attributes that should be cast.
     */
    protected $casts = [
        'size_bytes' => 'integer',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    /**
     * Get the blob storage record associated with this blob (for database backend).
     */
    public function blobStorage(): HasOne
    {
        return $this->hasOne(BlobStorage::class, 'blob_id', 'id');
    }

    /**
     * Check if this blob uses database storage backend.
     */
    public function usesDatabaseStorage(): bool
    {
        return $this->storage_backend === 'database';
    }

    /**
     * Get human-readable file size.
     */
    public function getFormattedSizeAttribute(): string
    {
        $bytes = $this->size_bytes;
        $units = ['B', 'KB', 'MB', 'GB', 'TB'];
        
        for ($i = 0; $bytes >= 1024 && $i < count($units) - 1; $i++) {
            $bytes /= 1024;
        }
        
        return number_format($bytes, 2) . ' ' . $units[$i];
    }
}
