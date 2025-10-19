<?php

namespace App\Contracts;

/**
 * Storage Driver Interface
 * 
 * Defines the contract that all storage drivers must implement
 * for storing and retrieving blob data across different backends.
 */
interface StorageDriverInterface
{
    /**
     * Store blob data and return the storage path.
     * 
     * @param string $blobId Unique identifier for the blob
     * @param string $data Binary data to store
     * @param string $mimeType MIME type of the data
     * @return string Storage path or identifier
     * @throws \Exception If storage fails
     */
    public function store(string $blobId, string $data, string $mimeType): string;

    /**
     * Retrieve blob data by storage path.
     * 
     * @param string $storagePath Path or identifier where data is stored
     * @return string Binary data
     * @throws \Exception If retrieval fails or data not found
     */
    public function retrieve(string $storagePath): string;

    /**
     * Delete blob data by storage path.
     * 
     * @param string $storagePath Path or identifier where data is stored
     * @return bool True if deletion was successful
     * @throws \Exception If deletion fails
     */
    public function delete(string $storagePath): bool;

    /**
     * Check if blob data exists at the given storage path.
     * 
     * @param string $storagePath Path or identifier where data is stored
     * @return bool True if data exists
     */
    public function exists(string $storagePath): bool;

    /**
     * Get the size of stored data in bytes.
     * 
     * @param string $storagePath Path or identifier where data is stored
     * @return int Size in bytes
     * @throws \Exception If size cannot be determined
     */
    public function getSize(string $storagePath): int;

    /**
     * Get the backend type identifier.
     * 
     * @return string Backend type (s3, database, local, ftp)
     */
    public function getBackendType(): string;

    /**
     * Validate the driver configuration.
     * 
     * @return bool True if configuration is valid
     */
    public function isConfigured(): bool;
}