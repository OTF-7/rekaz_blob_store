<?php

namespace App\Services\Storage;

use App\Contracts\StorageDriverInterface;
use App\Models\BlobStorage;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Exception;

/**
 * Database Storage Driver
 * 
 * Stores blob data directly in the database using base64 encoding.
 * Suitable for small files and when database storage is preferred.
 */
class DatabaseStorageDriver implements StorageDriverInterface
{
    /**
     * Store blob data in the database.
     */
    public function store(string $blobId, string $data, string $mimeType): string
    {
        try {
            $blobStorage = BlobStorage::create([
                'blob_id' => $blobId,
                'data' => $data, // Will be automatically base64 encoded by the model
            ]);

            return (string) $blobStorage->id;
        } catch (Exception $e) {
            throw new Exception("Failed to store blob in database: {$e->getMessage()}", 0, $e);
        }
    }

    /**
     * Retrieve blob data from the database.
     * For database storage, storagePath is empty, so we need to use the blob_id.
     */
    public function retrieve(string $storagePath, string $blobId = null): string
    {
        try {
            // For database storage, we use blob_id to find the record
            $blobStorage = BlobStorage::where('blob_id', $blobId)->firstOrFail();
            return $blobStorage->getDecodedData();
        } catch (ModelNotFoundException $e) {
            throw new Exception("Blob not found in database storage: {$blobId}", 404, $e);
        } catch (Exception $e) {
            throw new Exception("Failed to retrieve blob from database: {$e->getMessage()}", 0, $e);
        }
    }

    /**
     * Delete blob data from the database.
     */
    public function delete(string $storagePath): bool
    {
        try {
            $blobStorage = BlobStorage::find($storagePath);
            
            if (!$blobStorage) {
                return false;
            }

            return $blobStorage->delete();
        } catch (Exception $e) {
            throw new Exception("Failed to delete blob from database: {$e->getMessage()}", 0, $e);
        }
    }

    /**
     * Check if blob data exists in the database.
     */
    public function exists(string $storagePath): bool
    {
        try {
            return BlobStorage::where('id', $storagePath)->exists();
        } catch (Exception $e) {
            return false;
        }
    }

    /**
     * Get the size of stored data in bytes.
     */
    public function getSize(string $storagePath): int
    {
        try {
            $blobStorage = BlobStorage::findOrFail($storagePath);
            // Calculate size from base64 encoded data
            $base64Data = $blobStorage->data;
            $decodedSize = (strlen($base64Data) * 3) / 4;
            
            // Account for padding
            $padding = substr_count(substr($base64Data, -2), '=');
            return (int) ($decodedSize - $padding);
        } catch (ModelNotFoundException $e) {
            throw new Exception("Blob not found in database storage: {$storagePath}", 404, $e);
        } catch (Exception $e) {
            throw new Exception("Failed to get blob size from database: {$e->getMessage()}", 0, $e);
        }
    }

    /**
     * Get the backend type identifier.
     */
    public function getBackendType(): string
    {
        return 'database';
    }

    /**
     * Validate the driver configuration.
     * Database driver doesn't need additional configuration.
     */
    public function isConfigured(): bool
    {
        return true;
    }
}