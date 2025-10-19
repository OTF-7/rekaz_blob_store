<?php

namespace App\Services\Storage;

use App\Contracts\StorageDriverInterface;
use Exception;
use League\Flysystem\Filesystem;
use League\Flysystem\Ftp\FtpAdapter;
use League\Flysystem\Ftp\FtpConnectionOptions;
use League\Flysystem\FilesystemException;
use League\Flysystem\UnableToReadFile;
use League\Flysystem\UnableToWriteFile;
use League\Flysystem\UnableToDeleteFile;

/**
 * FTP Storage Driver
 * 
 * Stores blob data on FTP servers using League Flysystem.
 * Suitable for remote storage on FTP servers.
 */
class FtpStorageDriver implements StorageDriverInterface
{
    private ?array $config = null;
    private ?Filesystem $filesystem = null;

    public function __construct()
    {
        $this->loadConfiguration();
        $this->initializeFilesystem();
    }

    /**
     * Load configuration from environment variables.
     */
    private function loadConfiguration(): void
    {
        $this->config = [
            'host' => env('FTP_HOST'),
            'username' => env('FTP_USERNAME'),
            'password' => env('FTP_PASSWORD'),
            'port' => (int) env('FTP_PORT', 21),
            'root' => env('FTP_ROOT', '/'),
            'passive' => (bool) env('FTP_PASSIVE', true),
            'ssl' => (bool) env('FTP_SSL', false),
            'timeout' => (int) env('FTP_TIMEOUT', 30),
            'utf8' => (bool) env('FTP_UTF8', false),
            'prefix' => 'blobs'
        ];
    }

    /**
     * Initialize the FTP filesystem.
     */
    private function initializeFilesystem(): void
    {
        try {
            
            $connectionOptions = [
                'host' => $this->config['host'],
                'root' => $this->config['root'] ?? '/',
                'username' => $this->config['username'],
                'password' => $this->config['password'], // Try without URL encoding first
                'port' => (int)($this->config['port'] ?? 21),
                'ssl' => (bool)($this->config['ssl'] ?? false),
                'timeout' => (int)($this->config['timeout'] ?? 30),
                'utf8' => (bool)($this->config['utf8'] ?? false),
                'passive' => (bool)($this->config['passive'] ?? true),
                'transferMode' => FTP_BINARY,
            ];
            
            \Log::info("FtpStorageDriver: Connection options - Host: {$connectionOptions['host']}, Port: {$connectionOptions['port']}, SSL: " . ($connectionOptions['ssl'] ? 'true' : 'false') . ", Passive: " . ($connectionOptions['passive'] ? 'true' : 'false'));
            
            $adapter = new FtpAdapter(FtpConnectionOptions::fromArray($connectionOptions));
            
            $this->filesystem = new Filesystem($adapter);
            

        } catch (Exception $e) {
            $this->filesystem = null;
            throw new Exception('Failed to initialize FTP filesystem: ' . $e->getMessage());
        }
    }

    /**
     * Get the FTP path for a blob.
     */
    private function getBlobPath(string $blobId): string
    {
        $prefix = $this->config['prefix'] ?? 'blobs';
        
        // Store files directly in the blobs directory without nested subdirectories
        return $prefix . '/' . $blobId;
    }

    /**
     * Store blob data on the FTP server.
     */
    public function store(string $blobId, string $data, string $mimeType): string
    {
        if (!$this->filesystem) {
            throw new Exception('FTP filesystem not initialized');
        }

        try {
            $path = $this->getBlobPath($blobId);
            
            // Create parent directories if they don't exist
            $directory = dirname($path);
            if (!$this->filesystem->directoryExists($directory)) {
                $this->filesystem->createDirectory($directory);
            }
            
            $this->filesystem->write($path, $data);
            
            return $path;
        } catch (UnableToWriteFile $e) {
            throw new Exception("Failed to store blob on FTP server: {$e->getMessage()}", 0, $e);
        } catch (Exception $e) {
            throw new Exception("Failed to store blob on FTP server: {$e->getMessage()}", 0, $e);
        }
    }

    /**
     * Retrieve blob data from the FTP server.
     */
    public function retrieve(string $storagePath): string
    {
        if (!$this->filesystem) {
            throw new Exception('FTP filesystem not initialized');
        }

        try {
            return $this->filesystem->read($storagePath);
        } catch (UnableToReadFile $e) {
            if (str_contains($e->getMessage(), 'not found') || str_contains($e->getMessage(), 'does not exist')) {
                throw new Exception("Blob not found on FTP server: {$storagePath}", 404, $e);
            }
            throw new Exception("Failed to retrieve blob from FTP server: {$e->getMessage()}", 0, $e);
        } catch (Exception $e) {
            throw new Exception("Failed to retrieve blob from FTP server: {$e->getMessage()}", 0, $e);
        }
    }

    /**
     * Delete blob data from the FTP server.
     */
    public function delete(string $storagePath): bool
    {
        if (!$this->filesystem) {
            throw new Exception('FTP filesystem not initialized');
        }

        try {
            if (!$this->filesystem->fileExists($storagePath)) {
                return false;
            }

            $this->filesystem->delete($storagePath);
            return true;
        } catch (UnableToDeleteFile $e) {
            throw new Exception("Failed to delete blob from FTP server: {$e->getMessage()}", 0, $e);
        } catch (Exception $e) {
            throw new Exception("Failed to delete blob from FTP server: {$e->getMessage()}", 0, $e);
        }
    }

    /**
     * Check if blob data exists on the FTP server.
     */
    public function exists(string $storagePath): bool
    {
        if (!$this->filesystem) {
            return false;
        }

        try {
            return $this->filesystem->fileExists($storagePath);
        } catch (Exception $e) {
            return false;
        }
    }

    /**
     * Get the size of stored data in bytes.
     */
    public function getSize(string $storagePath): int
    {
        if (!$this->filesystem) {
            throw new Exception('FTP filesystem not initialized');
        }

        try {
            if (!$this->filesystem->fileExists($storagePath)) {
                throw new Exception("Blob not found on FTP server: {$storagePath}", 404);
            }

            return $this->filesystem->fileSize($storagePath);
        } catch (FilesystemException $e) {
            if (str_contains($e->getMessage(), 'not found') || str_contains($e->getMessage(), 'does not exist')) {
                throw new Exception("Blob not found on FTP server: {$storagePath}", 404, $e);
            }
            throw new Exception("Failed to get blob size from FTP server: {$e->getMessage()}", 0, $e);
        } catch (Exception $e) {
            if ($e->getCode() === 404) {
                throw $e;
            }
            throw new Exception("Failed to get blob size from FTP server: {$e->getMessage()}", 0, $e);
        }
    }

    /**
     * Get the backend type identifier.
     */
    public function getBackendType(): string
    {
        return 'ftp';
    }

    /**
     * Check if the FTP driver is properly configured.
     */
    public function isConfigured(): bool
    {
        $hasHost = !empty($this->config['host']);
        $hasUsername = !empty($this->config['username']);
        $hasPassword = !empty($this->config['password']);
        $hasFilesystem = $this->filesystem !== null;
        
        return $hasHost && $hasUsername && $hasPassword && $hasFilesystem;
    }
}