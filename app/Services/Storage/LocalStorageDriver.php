<?php

namespace App\Services\Storage;

use App\Contracts\StorageDriverInterface;
use Exception;
use Illuminate\Support\Facades\File;

/**
 * Local File System Storage Driver
 * 
 * Stores blob data in the local file system.
 * Suitable for development and when local storage is preferred.
 */
class LocalStorageDriver implements StorageDriverInterface
{
    private ?array $config = null;

    public function __construct()
    {
        $this->loadConfiguration();
    }

    /**
     * Load configuration from config file.
     */
    private function loadConfiguration(): void
    {
        $config = config('storage_backends.backends.local');
        
        $this->config = [
            'storage_path' => $config['storage_path'] ?? storage_path('app/blobs'),
            'create_directories' => $config['create_directories'] ?? true,
            'permissions' => $config['permissions'] ?? '755',
        ];
    }

    /**
     * Get the full file path for a blob.
     */
    private function getFilePath(string $blobId): string
    {
        $storagePath = $this->config['storage_path'];
        
        // Store files directly in the blobs directory without nested folders
        return $storagePath . '/' . $blobId;
    }

    /**
     * Store blob data in the local file system.
     */
    public function store(string $blobId, string $data, string $mimeType): string
    {
        try {
            $filePath = $this->getFilePath($blobId);
            $directory = dirname($filePath);

            // Ensure the base blobs directory exists
            if (!File::isDirectory($directory)) {
                File::makeDirectory($directory, 0755, true);
            }

            // Write the file
            if (File::put($filePath, $data) === false) {
                throw new Exception('Failed to write file to disk');
            }

            return $filePath;
        } catch (Exception $e) {
            throw new Exception("Failed to store blob in local storage: {$e->getMessage()}", 0, $e);
        }
    }

    /**
     * Retrieve blob data from the local file system.
     */
    public function retrieve(string $storagePath): string
    {
        try {
            if (!File::exists($storagePath)) {
                throw new Exception("File not found: {$storagePath}", 404);
            }

            $data = File::get($storagePath);
            
            if ($data === false) {
                throw new Exception("Failed to read file: {$storagePath}");
            }

            return $data;
        } catch (Exception $e) {
            if ($e->getCode() === 404) {
                throw $e;
            }
            throw new Exception("Failed to retrieve blob from local storage: {$e->getMessage()}", 0, $e);
        }
    }

    /**
     * Delete blob data from the local file system.
     */
    public function delete(string $storagePath): bool
    {
        try {
            if (!File::exists($storagePath)) {
                return false;
            }

            return File::delete($storagePath);
        } catch (Exception $e) {
            throw new Exception("Failed to delete blob from local storage: {$e->getMessage()}", 0, $e);
        }
    }

    /**
     * Check if blob data exists in the local file system.
     */
    public function exists(string $storagePath): bool
    {
        return File::exists($storagePath);
    }

    /**
     * Get the size of stored data in bytes.
     */
    public function getSize(string $storagePath): int
    {
        try {
            if (!File::exists($storagePath)) {
                throw new Exception("File not found: {$storagePath}", 404);
            }

            return File::size($storagePath);
        } catch (Exception $e) {
            if ($e->getCode() === 404) {
                throw $e;
            }
            throw new Exception("Failed to get blob size from local storage: {$e->getMessage()}", 0, $e);
        }
    }

    /**
     * Get the backend type identifier.
     */
    public function getBackendType(): string
    {
        return 'local';
    }

    /**
     * Validate the driver configuration.
     */
    public function isConfigured(): bool
    {
        if (!$this->config || !isset($this->config['storage_path'])) {
            return false;
        }

        $storagePath = $this->config['storage_path'];
        
        // Check if directory exists or can be created
        if (!File::isDirectory($storagePath)) {
            try {
                File::makeDirectory($storagePath, 0755, true);
            } catch (Exception $e) {
                return false;
            }
        }

        // Check if directory is writable
        return File::isWritable($storagePath);
    }
}