<?php

namespace App\Services;

use App\Contracts\StorageDriverInterface;
use App\Services\Storage\DatabaseStorageDriver;
use App\Services\Storage\LocalStorageDriver;
use App\Services\Storage\S3StorageDriver;
use App\Services\Storage\FtpStorageDriver;
use Exception;
use InvalidArgumentException;

/**
 * Storage Manager
 *
 * Manages storage drivers and provides a unified interface for blob storage operations.
 * Handles driver selection, configuration, and fallback mechanisms.
 */
class StorageManager
{
    /**
     * Available storage drivers.
     */
    private array $drivers = [];

    /**
     * Default storage backend type.
     */
    private string $defaultBackend;

    public function __construct()
    {
        $this->defaultBackend = 'database';
        $this->initializeDrivers();
    }

    /**
     * Initialize all available storage drivers.
     */
    private function initializeDrivers(): void
    {
        $this->drivers = [
            'database' => new DatabaseStorageDriver(),
            'local' => new LocalStorageDriver(),
            's3' => new S3StorageDriver(),
            'ftp' => new FtpStorageDriver(),
        ];
    }

    /**
     * Get a storage driver by backend type.
     */
    public function getDriver(string $backendType): StorageDriverInterface
    {
        if (!isset($this->drivers[$backendType])) {
            throw new InvalidArgumentException("Unsupported storage backend: {$backendType}");
        }

        $driver = $this->drivers[$backendType];

        if (!$driver->isConfigured()) {
            throw new Exception("Storage driver '{$backendType}' is not properly configured");
        }

        return $driver;
    }

    /**
     * Get the default storage driver.
     */
    public function getDefaultDriver(): StorageDriverInterface
    {
        return $this->getDriver($this->defaultBackend);
    }

    /**
     * Get the best available storage driver based on configuration.
     */
    public function getBestAvailableDriver(): StorageDriverInterface
    {
        $configuredBackend = config('storage_backends.default', 'database');

        // Try to use the configured backend first
        if (isset($this->drivers[$configuredBackend])) {
            $driver = $this->drivers[$configuredBackend];
            if ($driver->isConfigured()) {
                \Log::info("Storage: Using configured backend '{$configuredBackend}'");
                return $driver;
            } else {
                \Log::warning("Storage: Configured backend '{$configuredBackend}' is not properly configured, falling back to database");
            }
        } else {
            \Log::warning("Storage: Configured backend '{$configuredBackend}' is not available, falling back to database");
        }

        // Fallback to database driver
        \Log::info("Storage: Using database backend as fallback");
        return $this->drivers['database'];
    }

    /**
     * Store blob data using the specified or best available driver.
     */
    public function store(string $blobId, string $data, string $mimeType, ?string $backendType = null): array
    {
        $driver = $backendType ? $this->getDriver($backendType) : $this->getBestAvailableDriver();

        try {
            $storagePath = $driver->store($blobId, $data, $mimeType);
            \Log::info("Storage: Successfully stored blob '{$blobId}' using {$driver->getBackendType()} backend");

            return [
                'backend_type' => $driver->getBackendType(),
                'storage_path' => $storagePath,
            ];
        } catch (Exception $e) {
            \Log::error("Storage: Failed to store blob '{$blobId}' using {$driver->getBackendType()} backend: {$e->getMessage()}");

            // If specified backend fails and it's not the database backend, try database as fallback
            if ($backendType && $backendType !== 'database') {
                try {
                    \Log::info("Storage: Attempting fallback to database backend for blob '{$blobId}'");
                    $fallbackDriver = $this->getDriver('database');
                    $storagePath = $fallbackDriver->store($blobId, $data, $mimeType);
                    \Log::info("Storage: Successfully stored blob '{$blobId}' using database fallback");

                    return [
                        'backend_type' => $fallbackDriver->getBackendType(),
                        'storage_path' => $storagePath,
                    ];
                } catch (Exception $fallbackException) {
                    \Log::error("Storage: Database fallback also failed for blob '{$blobId}': {$fallbackException->getMessage()}");
                    // If fallback also fails, throw the original exception
                    throw $e;
                }
            }

            throw $e;
        }
    }

    /**
     * Retrieve blob data using the appropriate driver.
     */
    public function retrieve(string $backendType, string $storagePath, string $blobId = null): string
    {
        $driver = $this->getDriver($backendType);

        // For database storage, we need to pass the blob_id
        if ($backendType === 'database' && $driver instanceof \App\Services\Storage\DatabaseStorageDriver) {
            return $driver->retrieve($storagePath, $blobId);
        }

        return $driver->retrieve($storagePath);
    }

    /**
     * Delete blob data using the appropriate driver.
     */
    public function delete(string $backendType, string $storagePath): bool
    {
        $driver = $this->getDriver($backendType);
        return $driver->delete($storagePath);
    }

    /**
     * Check if blob data exists using the appropriate driver.
     */
    public function exists(string $backendType, string $storagePath): bool
    {
        try {
            $driver = $this->getDriver($backendType);
            return $driver->exists($storagePath);
        } catch (Exception $e) {
            return false;
        }
    }

    /**
     * Get the size of blob data using the appropriate driver.
     */
    public function getSize(string $backendType, string $storagePath): int
    {
        $driver = $this->getDriver($backendType);
        return $driver->getSize($storagePath);
    }

    /**
     * Get all available backend types.
     */
    public function getAvailableBackends(): array
    {
        return array_keys($this->drivers);
    }

    /**
     * Get the current active backend type.
     */
    public function getCurrentBackend(): string
    {
        return strtolower(config('storage_backends.default', $this->defaultBackend));
    }

    /**
     * Get all configured backend types based on environment variables.
     */
    public function getConfiguredBackends(): array
    {
        $configured = ['database']; // Database is always available

        // Check if local storage is configured
        if (env('LOCAL_STORAGE_PATH')) {
            $configured[] = 'local';
        }

        // Check if S3 is configured
        if (env('S3_ENDPOINT') && env('S3_BUCKET') && env('S3_ACCESS_KEY') && env('S3_SECRET_KEY')) {
            $configured[] = 's3';
        }

        // Check if FTP is configured
        if (env('FTP_HOST') && env('FTP_USERNAME') && env('FTP_PASSWORD')) {
            $configured[] = 'ftp';
        }

        return $configured;
    }

    /**
     * Test a storage driver configuration.
     */
    public function testDriver(string $backendType): array
    {
        try {
            $driver = $this->getDriver($backendType);

            // Test with a small dummy file
            $testData = 'test-blob-data';
            $testBlobId = 'test-' . uniqid('', true);

            // Store test data
            $storagePath = $driver->store($testBlobId, $testData, 'text/plain');

            // Retrieve test data
            $retrievedData = $driver->retrieve($storagePath);

            // Verify data integrity
            $dataMatches = $retrievedData === $testData;

            // Check if exists
            $exists = $driver->exists($storagePath);

            // Get size
            $size = $driver->getSize($storagePath);

            // Clean up test data
            $driver->delete($storagePath);

            return [
                'success' => true,
                'configured' => true,
                'data_integrity' => $dataMatches,
                'exists_check' => $exists,
                'size_check' => $size === strlen($testData),
                'message' => 'Driver test completed successfully',
            ];
        } catch (Exception $e) {
            return [
                'success' => false,
                'configured' => isset($this->drivers[$backendType]) && $this->drivers[$backendType]->isConfigured(),
                'error' => $e->getMessage(),
                'message' => "Driver test failed: {$e->getMessage()}",
            ];
        }
    }
}
