<?php

namespace App\Services\Storage;

use App\Contracts\StorageDriverInterface;
use Exception;
use Illuminate\Support\Facades\Http;

/**
 * S3 Compatible Storage Driver
 * 
 * Stores blob data in S3-compatible storage using HTTP client.
 * Supports AWS S3 and other S3-compatible services like MinIO.
 */
class S3StorageDriver implements StorageDriverInterface
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
        $config = config('storage_backends.backends.s3');
        
        $this->config = [
            'endpoint' => $config['endpoint'] ?? null,
            'bucket' => $config['bucket'] ?? null,
            'access_key' => $config['access_key'] ?? null,
            'secret_key' => $config['secret_key'] ?? null,
            'region' => $config['region'] ?? 'us-east-1',
            'use_path_style_endpoint' => $config['use_path_style_endpoint'] ?? false,
            'prefix' => $config['prefix'] ?? 'blobs',
        ];
    }

    /**
     * Generate AWS Signature Version 4 for authentication.
     */
    private function generateSignature(string $method, string $uri, array $headers, string $payload = ''): array
    {
        $accessKey = $this->config['access_key'];
        $secretKey = $this->config['secret_key'];
        $region = $this->config['region'] ?? 'us-east-1';
        $service = 's3';
        
        $timestamp = gmdate('Ymd\THis\Z');
        $date = gmdate('Ymd');
        
        // Add required headers for signature
        $headers['X-Amz-Date'] = $timestamp;
        
        // Create canonical request
        $canonicalHeaders = '';
        $signedHeaders = '';
        
        // Sort headers by lowercase key
        $sortedHeaders = [];
        foreach ($headers as $key => $value) {
            $sortedHeaders[strtolower($key)] = trim($value);
        }
        ksort($sortedHeaders);
        
        foreach ($sortedHeaders as $key => $value) {
            $canonicalHeaders .= $key . ':' . $value . "\n";
            $signedHeaders .= $key . ';';
        }
        
        $signedHeaders = rtrim($signedHeaders, ';');
        
        // Use the URI as-is for MinIO compatibility
        $canonicalUri = $uri;
        
        $canonicalRequest = $method . "\n" .
                           $canonicalUri . "\n" .
                           "\n" . // Query string (empty for our use case)
                           $canonicalHeaders . "\n" .
                           $signedHeaders . "\n" .
                           hash('sha256', $payload);
        
        // Create string to sign
        $algorithm = 'AWS4-HMAC-SHA256';
        $credentialScope = $date . '/' . $region . '/' . $service . '/aws4_request';
        $stringToSign = $algorithm . "\n" .
                       $timestamp . "\n" .
                       $credentialScope . "\n" .
                       hash('sha256', $canonicalRequest);
        
        // Calculate signature
        $dateKey = hash_hmac('sha256', $date, 'AWS4' . $secretKey, true);
        $dateRegionKey = hash_hmac('sha256', $region, $dateKey, true);
        $dateRegionServiceKey = hash_hmac('sha256', $service, $dateRegionKey, true);
        $signingKey = hash_hmac('sha256', 'aws4_request', $dateRegionServiceKey, true);
        $signature = hash_hmac('sha256', $stringToSign, $signingKey);
        
        // Create authorization header
        $authorization = $algorithm . ' ' .
                        'Credential=' . $accessKey . '/' . $credentialScope . ', ' .
                        'SignedHeaders=' . $signedHeaders . ', ' .
                        'Signature=' . $signature;
        
        return [
            'Authorization' => $authorization,
            'X-Amz-Date' => $timestamp,
        ];
    }

    /**
     * Get the S3 object key for a blob.
     */
    private function getObjectKey(string $blobId): string
    {
        $prefix = $this->config['prefix'] ?? 'blobs';
        return $prefix . '/' . $blobId;
    }

    /**
     * Get the full S3 URL for an object.
     */
    private function getObjectUrl(string $objectKey): string
    {
        $endpoint = rtrim($this->config['endpoint'], '/');
        $bucket = $this->config['bucket'];
        
        return $endpoint . '/' . $bucket . '/' . $objectKey;
    }

    /**
     * Store blob data in S3-compatible storage.
     */
    public function store(string $blobId, string $data, string $mimeType): string
    {
        try {
            $objectKey = $this->getObjectKey($blobId);
            $url = $this->getObjectUrl($objectKey);
            
            $parsedUrl = parse_url($this->config['endpoint']);
            $host = $parsedUrl['host'];
            if (isset($parsedUrl['port'])) {
                $host .= ':' . $parsedUrl['port'];
            }
            
            // Use minimal headers for signature (only Host) - same as successful bucket listing
            $signatureHeaders = [
                'Host' => $host,
            ];
            
            // Use empty payload for signature calculation (MinIO compatibility)
            $authHeaders = $this->generateSignature('PUT', '/' . $this->config['bucket'] . '/' . $objectKey, $signatureHeaders, '');
            
            // Add all headers for the actual request
            $requestHeaders = [
                'Content-Type' => $mimeType,
                'Content-Length' => (string) strlen($data),
                'Host' => $host,
                'Authorization' => $authHeaders['Authorization'],
                'X-Amz-Date' => $authHeaders['X-Amz-Date'],
            ];
            
            $response = Http::withHeaders($requestHeaders)->withBody($data, $mimeType)->put($url);
            
            if (!$response->successful()) {
                throw new Exception("S3 PUT request failed: {$response->status()} - {$response->body()}");
            }
            
            return $objectKey;
        } catch (Exception $e) {
            throw new Exception("Failed to store blob in S3 storage: {$e->getMessage()}", 0, $e);
        }
    }

    /**
     * Retrieve blob data from S3-compatible storage.
     */
    public function retrieve(string $storagePath): string
    {
        try {
            // If storagePath doesn't contain the prefix, it might be just the blob ID
            // In that case, we need to construct the proper object key
            $objectKey = $storagePath;
            $prefix = $this->config['prefix'] ?? 'blobs';
            
            // Check if the storage path already contains the prefix
            if (!str_starts_with($storagePath, $prefix . '/')) {
                // If not, treat it as a blob ID and construct the object key
                $objectKey = $this->getObjectKey($storagePath);
            }
            
            $url = $this->getObjectUrl($objectKey);
            
            $parsedUrl = parse_url($this->config['endpoint']);
            $host = $parsedUrl['host'];
            if (isset($parsedUrl['port'])) {
                $host .= ':' . $parsedUrl['port'];
            }
            
            $headers = [
                'Host' => $host,
            ];
            
            $authHeaders = $this->generateSignature('GET', '/' . $this->config['bucket'] . '/' . $objectKey, $headers);
            $headers = array_merge($headers, $authHeaders);
            
            $response = Http::withHeaders($headers)->get($url);
            
            if ($response->status() === 404) {
                throw new Exception("Blob not found in S3 storage: {$objectKey}", 404);
            }
            
            if (!$response->successful()) {
                throw new Exception("S3 GET request failed: {$response->status()} - {$response->body()}");
            }
            
            return $response->body();
        } catch (Exception $e) {
            if ($e->getCode() === 404) {
                throw $e;
            }
            throw new Exception("Failed to retrieve blob from S3 storage: {$e->getMessage()}", 0, $e);
        }
    }

    /**
     * Delete blob data from S3-compatible storage.
     */
    public function delete(string $storagePath): bool
    {
        try {
            // If storagePath doesn't contain the prefix, it might be just the blob ID
            // In that case, we need to construct the proper object key
            $objectKey = $storagePath;
            $prefix = $this->config['prefix'] ?? 'blobs';
            
            // Check if the storage path already contains the prefix
            if (!str_starts_with($storagePath, $prefix . '/')) {
                // If not, treat it as a blob ID and construct the object key
                $objectKey = $this->getObjectKey($storagePath);
            }
            
            $url = $this->getObjectUrl($objectKey);
            
            $parsedUrl = parse_url($this->config['endpoint']);
            $host = $parsedUrl['host'];
            if (isset($parsedUrl['port'])) {
                $host .= ':' . $parsedUrl['port'];
            }
            
            $headers = [
                'Host' => $host,
            ];
            
            $authHeaders = $this->generateSignature('DELETE', '/' . $this->config['bucket'] . '/' . $objectKey, $headers);
            $headers = array_merge($headers, $authHeaders);
            
            $response = Http::withHeaders($headers)->delete($url);
            
            if ($response->status() === 404) {
                return false;
            }
            
            return $response->successful();
        } catch (Exception $e) {
            throw new Exception("Failed to delete blob from S3 storage: {$e->getMessage()}", 0, $e);
        }
    }

    /**
     * Check if blob data exists in S3-compatible storage.
     */
    public function exists(string $storagePath): bool
    {
        try {
            // If storagePath doesn't contain the prefix, it might be just the blob ID
            // In that case, we need to construct the proper object key
            $objectKey = $storagePath;
            $prefix = $this->config['prefix'] ?? 'blobs';
            
            // Check if the storage path already contains the prefix
            if (!str_starts_with($storagePath, $prefix . '/')) {
                // If not, treat it as a blob ID and construct the object key
                $objectKey = $this->getObjectKey($storagePath);
            }
            
            $url = $this->getObjectUrl($objectKey);
            
            $parsedUrl = parse_url($this->config['endpoint']);
            $host = $parsedUrl['host'];
            if (isset($parsedUrl['port'])) {
                $host .= ':' . $parsedUrl['port'];
            }
            
            $headers = [
                'Host' => $host,
            ];
            
            $authHeaders = $this->generateSignature('HEAD', '/' . $this->config['bucket'] . '/' . $objectKey, $headers);
            $headers = array_merge($headers, $authHeaders);
            
            $response = Http::withHeaders($headers)->head($url);
            
            return $response->successful();
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
            // If storagePath doesn't contain the prefix, it might be just the blob ID
            // In that case, we need to construct the proper object key
            $objectKey = $storagePath;
            $prefix = $this->config['prefix'] ?? 'blobs';
            
            // Check if the storage path already contains the prefix
            if (!str_starts_with($storagePath, $prefix . '/')) {
                // If not, treat it as a blob ID and construct the object key
                $objectKey = $this->getObjectKey($storagePath);
            }
            
            $url = $this->getObjectUrl($objectKey);
            
            $parsedUrl = parse_url($this->config['endpoint']);
            $host = $parsedUrl['host'];
            if (isset($parsedUrl['port'])) {
                $host .= ':' . $parsedUrl['port'];
            }
            
            $headers = [
                'Host' => $host,
            ];
            
            $authHeaders = $this->generateSignature('HEAD', '/' . $this->config['bucket'] . '/' . $objectKey, $headers);
            $headers = array_merge($headers, $authHeaders);
            
            $response = Http::withHeaders($headers)->head($url);
            
            if ($response->status() === 404) {
                throw new Exception("Blob not found in S3 storage: {$objectKey}", 404);
            }
            
            if (!$response->successful()) {
                throw new Exception("S3 HEAD request failed: {$response->status()}");
            }
            
            $contentLength = $response->header('Content-Length');
            
            if ($contentLength === null) {
                throw new Exception('Content-Length header not found in S3 response');
            }
            
            return (int) $contentLength;
        } catch (Exception $e) {
            if ($e->getCode() === 404) {
                throw $e;
            }
            throw new Exception("Failed to get blob size from S3 storage: {$e->getMessage()}", 0, $e);
        }
    }

    /**
     * Get the backend type identifier.
     */
    public function getBackendType(): string
    {
        return 's3';
    }

    /**
     * Validate the driver configuration.
     */
    public function isConfigured(): bool
    {
        return $this->config !== null &&
               isset($this->config['endpoint']) &&
               isset($this->config['bucket']) &&
               isset($this->config['access_key']) &&
               isset($this->config['secret_key']);
    }
}