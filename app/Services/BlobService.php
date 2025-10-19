<?php

namespace App\Services;

use App\Models\Blob;
use App\Models\User;
use Exception;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * Blob Service
 * 
 * Handles business logic for blob storage operations including
 * file upload, retrieval, validation, and metadata management.
 */
class BlobService
{
    private StorageManager $storageManager;

    public function __construct(StorageManager $storageManager)
    {
        $this->storageManager = $storageManager;
    }

    /**
     * Unified store method for all blob storage scenarios.
     * 
     * @param UploadedFile|string $source Either an UploadedFile or raw content string
     * @param string|null $blobId Optional custom blob ID (if null, generates UUID)
     * @param string|null $originalFilename Original filename (required for content, auto-detected for files)
     * @param string|null $mimeType MIME type (required for content, auto-detected for files)
     * @param User|null $user Associated user
     * @param string|null $preferredBackend Preferred storage backend
     * @return Blob
     * @throws Exception
     */
    public function store(
        UploadedFile|string $source,
        ?string $blobId = null,
        ?string $originalFilename = null,
        ?string $mimeType = null,
        ?User $user = null,
        ?string $preferredBackend = null
    ): Blob {
        // Handle different source types
        if ($source instanceof UploadedFile) {
            // Validate uploaded file
            $this->validateFile($source);
            
            $content = $source->get();
            $originalFilename = $originalFilename ?? $source->getClientOriginalName();
            $mimeType = $mimeType ?? $source->getMimeType() ?? 'application/octet-stream';
        } else {
            // Raw content string
            $content = $source;
            if (!$originalFilename) {
                $originalFilename = $blobId ?? 'blob-' . time();
            }
            if (!$mimeType) {
                throw new Exception('MIME type is required when storing raw content');
            }
        }

        $sizeBytes = strlen($content);
        $checksumMd5 = md5($content);

        // Generate blob ID if not provided
        if (!$blobId) {
            $blobId = $this->generateBlobId();
            
            // Check for duplicate based on checksum (only for auto-generated IDs)
            $existingBlob = Blob::where('checksum_md5', $checksumMd5)
                ->where('size_bytes', $sizeBytes)
                ->first();

            if ($existingBlob && $this->verifyBlobIntegrity($existingBlob)) {
                Log::info('Duplicate blob detected, returning existing blob', [
                    'existing_id' => $existingBlob->id,
                    'checksum' => $checksumMd5,
                ]);
                return $existingBlob;
            }
        } else {
            // Check if custom blob ID already exists
            if (Blob::where('id', $blobId)->exists()) {
                throw new Exception("Blob with ID '{$blobId}' already exists", 409);
            }
        }

        DB::beginTransaction();

        try {
            // Get the preferred backend type or use best available
            if ($preferredBackend) {
                $driver = $this->storageManager->getDriver($preferredBackend);
                $actualBackendType = $driver->getBackendType();
            } else {
                $driver = $this->storageManager->getBestAvailableDriver();
                $actualBackendType = $driver->getBackendType();
            }

            // Create blob metadata record first
            $blob = Blob::create([
                'id' => $blobId,
                'original_filename' => $originalFilename,
                'size_bytes' => $sizeBytes,
                'mime_type' => $mimeType,
                'storage_backend' => $actualBackendType,
                'storage_path' => '', // Will be updated after storage
                'checksum_md5' => $checksumMd5,
            ]);

            // Store the blob data using storage manager
            $storageResult = $this->storageManager->store($blobId, $content, $mimeType, $actualBackendType);

            // Update both backend type and storage path based on actual result
            // (StorageManager may fallback to database if the preferred backend fails)
            $actualBackendUsed = $storageResult['backend_type'];
            $storagePath = $actualBackendUsed === 'database' ? '' : $storageResult['storage_path'];
            
            $blob->update([
                'storage_backend' => $actualBackendUsed,
                'storage_path' => $storagePath,
            ]);

            DB::commit();

            Log::info('Blob stored successfully', [
                'blob_id' => $blobId,
                'backend' => $storageResult['backend_type'],
                'size' => $sizeBytes,
                'filename' => $originalFilename,
                'source_type' => $source instanceof UploadedFile ? 'file' : 'content',
            ]);

            return $blob;
        } catch (Exception $e) {
            DB::rollBack();
            
            Log::error('Failed to store blob', [
                'blob_id' => $blobId,
                'error' => $e->getMessage(),
                'filename' => $originalFilename,
            ]);

            throw new Exception("Failed to store blob: {$e->getMessage()}", 0, $e);
        }
    }

    /**
     * Retrieve a blob by ID.
     */
    public function retrieve(string $blobId): array
    {
        $blob = Blob::find($blobId);

        if (!$blob) {
            throw new Exception("Blob not found: {$blobId}", 404);
        }

        try {
            // Retrieve blob data from storage
            $content = $this->storageManager->retrieve($blob->storage_backend, $blob->storage_path, $blob->id);

            // Verify data integrity
            $actualChecksum = md5($content);
            if ($actualChecksum !== $blob->checksum_md5) {
                Log::warning('Blob integrity check failed', [
                    'blob_id' => $blobId,
                    'expected_checksum' => $blob->checksum_md5,
                    'actual_checksum' => $actualChecksum,
                ]);
                throw new Exception('Blob data integrity check failed');
            }

            return [
                'blob' => $blob,
                'content' => $content,
            ];
        } catch (Exception $e) {
            Log::error('Failed to retrieve blob', [
                'blob_id' => $blobId,
                'backend' => $blob->storage_backend,
                'error' => $e->getMessage(),
            ]);

            if ($e->getCode() === 404) {
                throw new Exception("Blob data not found: {$blobId}", 404, $e);
            }

            throw new Exception("Failed to retrieve blob: {$e->getMessage()}", 0, $e);
        }
    }

    /**
     * Delete a blob by ID.
     */
    public function delete(string $blobId): bool
    {
        $blob = Blob::find($blobId);

        if (!$blob) {
            return false;
        }

        DB::beginTransaction();

        try {
            // Delete from storage backend
            $this->storageManager->delete($blob->storage_backend, $blob->storage_path);

            // Delete blob metadata
            $blob->delete();

            DB::commit();

            Log::info('Blob deleted successfully', [
                'blob_id' => $blobId,
                'backend' => $blob->storage_backend,
            ]);

            return true;
        } catch (Exception $e) {
            DB::rollBack();

            Log::error('Failed to delete blob', [
                'blob_id' => $blobId,
                'backend' => $blob->storage_backend,
                'error' => $e->getMessage(),
            ]);

            throw new Exception("Failed to delete blob: {$e->getMessage()}", 0, $e);
        }
    }

    /**
     * Get blob metadata by ID.
     */
    public function getMetadata(string $blobId): ?Blob
    {
        return Blob::find($blobId);
    }

    /**
     * List blobs with pagination.
     */
    public function listBlobs(int $page = 1, int $perPage = 20, ?string $mimeTypeFilter = null): array
    {
        $query = Blob::query()->orderBy('created_at', 'desc');

        if ($mimeTypeFilter) {
            $query->where('mime_type', 'like', $mimeTypeFilter . '%');
        }

        $blobs = $query->paginate($perPage, ['*'], 'page', $page);

        return [
            'data' => $blobs->items(),
            'pagination' => [
                'current_page' => $blobs->currentPage(),
                'per_page' => $blobs->perPage(),
                'total' => $blobs->total(),
                'last_page' => $blobs->lastPage(),
                'has_more' => $blobs->hasMorePages(),
            ],
        ];
    }

    /**
     * Get storage statistics.
     */
    public function getStorageStats(): array
    {
        $stats = DB::table('blobs')
            ->select(
                'storage_backend',
                DB::raw('COUNT(*) as blob_count'),
                DB::raw('SUM(size_bytes) as total_size')
            )
            ->groupBy('storage_backend')
            ->get()
            ->keyBy('storage_backend')
            ->toArray();

        $totalBlobs = array_sum(array_column($stats, 'blob_count'));
        $totalSize = array_sum(array_column($stats, 'total_size'));

        return [
            'total_blobs' => $totalBlobs,
            'total_size_bytes' => $totalSize,
            'total_size_formatted' => $this->formatBytes($totalSize),
            'backend_distribution' => $stats,
        ];
    }

    /**
     * Verify blob integrity.
     */
    public function verifyBlobIntegrity(Blob $blob): bool
    {
        try {
            $content = $this->storageManager->retrieve($blob->storage_backend, $blob->storage_path, $blob->id);
            $actualChecksum = md5($content);
            return $actualChecksum === $blob->checksum_md5;
        } catch (Exception $e) {
            return false;
        }
    }

    /**
     * Generate a unique blob ID.
     */
    private function generateBlobId(): string
    {
        do {
            $blobId = Str::uuid()->toString();
        } while (Blob::where('id', $blobId)->exists());

        return $blobId;
    }

    /**
     * Validate uploaded file.
     */
    private function validateFile(UploadedFile $file): void
    {
        if (!$file->isValid()) {
            throw new Exception('Invalid file upload');
        }

        $maxSize = config('storage.max_file_size', 100 * 1024 * 1024); // 100MB default
        if ($file->getSize() > $maxSize) {
            throw new Exception("File size exceeds maximum allowed size of {$this->formatBytes($maxSize)}");
        }

        $allowedMimeTypes = config('storage.allowed_mime_types', []);
        if (!empty($allowedMimeTypes) && !in_array($file->getMimeType(), $allowedMimeTypes)) {
            throw new Exception("File type '{$file->getMimeType()}' is not allowed");
        }
    }

    /**
     * Format bytes to human readable format.
     */
    private function formatBytes(int $bytes): string
    {
        $units = ['B', 'KB', 'MB', 'GB', 'TB'];
        $unitIndex = 0;
        $size = $bytes;

        while ($size >= 1024 && $unitIndex < count($units) - 1) {
            $size /= 1024;
            $unitIndex++;
        }

        return round($size, 2) . ' ' . $units[$unitIndex];
    }
}