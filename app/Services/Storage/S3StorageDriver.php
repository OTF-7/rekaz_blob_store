<?php

namespace App\Services\Storage;

use App\Contracts\StorageDriverInterface;
use Exception;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;

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
     * Load configuration from environment variables.
     */
    private function loadConfiguration(): void
    {
        $this->config = [
            'endpoint' => env('S3_ENDPOINT'),
            'bucket' => env('S3_BUCKET'),
            'access_key' => env('S3_ACCESS_KEY'),
            'secret_key' => env('S3_SECRET_KEY'),
            'region' => env('S3_REGION', 'us-east-1'),
            'use_path_style_endpoint' => env('S3_USE_PATH_STYLE_ENDPOINT', false),
            'prefix' => 'blobs'
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
        
        // Create canonical request
        $canonicalHeaders = '';
        $signedHeaders = '';
        ksort($headers);
        
        foreach ($headers as $key => $value) {
            $canonicalHeaders .= strtolower($key) . ':' . trim($value) . "\n";
            $signedHeaders .= strtolower($key) . ';';
        }
        
        $signedHeaders = rtrim($signedHeaders, ';');
        
        // URL encode the URI path for canonical request
        $canonicalUri = implode('/', array_map('rawurlencode', explode('/', $uri)));
        
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
            
            $headers = [
                'Content-Type' => $mimeType,
                'Content-Length' => (string) strlen($data),
                'Host' => $host,
            ];
            
            $authHeaders = $this->generateSignature('PUT', '/' . $this->config['bucket'] . '/' . $objectKey, $headers, $data);
            $headers = array_merge($headers, $authHeaders);
            
            $response = Http::withHeaders($headers)->put($url, $data);
            
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
            $url = $this->getObjectUrl($storagePath);
            
            $parsedUrl = parse_url($this->config['endpoint']);
            $host = $parsedUrl['host'];
            if (isset($parsedUrl['port'])) {
                $host .= ':' . $parsedUrl['port'];
            }
            
            $headers = [
                'Host' => $host,
            ];
            
            $authHeaders = $this->generateSignature('GET', '/' . $this->config['bucket'] . '/' . $storagePath, $headers);
            $headers = array_merge($headers, $authHeaders);
            
            $response = Http::withHeaders($headers)->get($url);
            
            if ($response->status() === 404) {
                throw new Exception("Blob not found in S3 storage: {$storagePath}", 404);
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
            $url = $this->getObjectUrl($storagePath);
            
            $parsedUrl = parse_url($this->config['endpoint']);
            $host = $parsedUrl['host'];
            if (isset($parsedUrl['port'])) {
                $host .= ':' . $parsedUrl['port'];
            }
            
            $headers = [
                'Host' => $host,
            ];
            
            $authHeaders = $this->generateSignature('DELETE', '/' . $this->config['bucket'] . '/' . $storagePath, $headers);
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
            $url = $this->getObjectUrl($storagePath);
            
            $parsedUrl = parse_url($this->config['endpoint']);
            $host = $parsedUrl['host'];
            if (isset($parsedUrl['port'])) {
                $host .= ':' . $parsedUrl['port'];
            }
            
            $headers = [
                'Host' => $host,
            ];
            
            $authHeaders = $this->generateSignature('HEAD', '/' . $this->config['bucket'] . '/' . $storagePath, $headers);
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
            $url = $this->getObjectUrl($storagePath);
            
            $parsedUrl = parse_url($this->config['endpoint']);
            $host = $parsedUrl['host'];
            if (isset($parsedUrl['port'])) {
                $host .= ':' . $parsedUrl['port'];
            }
            
            $headers = [
                'Host' => $host,
            ];
            
            $authHeaders = $this->generateSignature('HEAD', '/' . $this->config['bucket'] . '/' . $storagePath, $headers);
            $headers = array_merge($headers, $authHeaders);
            
            $response = Http::withHeaders($headers)->head($url);
            
            if ($response->status() === 404) {
                throw new Exception("Blob not found in S3 storage: {$storagePath}", 404);
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