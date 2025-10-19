<?php

namespace Tests\Unit;

use App\Models\Blob;
use App\Models\StorageConfiguration;
use App\Services\BlobService;
use App\Services\StorageManager;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class BlobServiceTest extends TestCase
{
    use RefreshDatabase;

    private BlobService $blobService;

    protected function setUp(): void
    {
        parent::setUp();
        $this->blobService = app(BlobService::class);
    }

    /**
     * Test storing a blob from uploaded file.
     */
    public function test_store_blob_from_uploaded_file(): void
    {
        $file = UploadedFile::fake()->create('test.txt', 100, 'text/plain');
        
        $blob = $this->blobService->store($file);
        
        $this->assertInstanceOf(Blob::class, $blob);
        $this->assertEquals('test.txt', $blob->original_filename);
        $this->assertEquals('text/plain', $blob->mime_type);
        $this->assertEquals('database', $blob->storage_backend);
        $this->assertNotNull($blob->id);
        $this->assertNotNull($blob->checksum_md5);
    }

    /**
     * Test storing a blob from raw content.
     */
    public function test_store_blob_from_content(): void
    {
        $content = 'This is test content';
        $filename = 'test.txt';
        $mimeType = 'text/plain';
        
        $blob = $this->blobService->store($content, null, $filename, $mimeType);
        
        $this->assertInstanceOf(Blob::class, $blob);
        $this->assertEquals($filename, $blob->original_filename);
        $this->assertEquals($mimeType, $blob->mime_type);
        $this->assertEquals(strlen($content), $blob->size_bytes);
        $this->assertEquals(md5($content), $blob->checksum_md5);
    }

    /**
     * Test retrieving a blob.
     */
    public function test_retrieve_blob(): void
    {
        $content = 'This is test content';
        $blob = $this->blobService->store($content, null, 'test.txt', 'text/plain');
        
        $result = $this->blobService->retrieve($blob->id);
        
        $this->assertIsArray($result);
        $this->assertArrayHasKey('blob', $result);
        $this->assertArrayHasKey('content', $result);
        $this->assertEquals($content, $result['content']);
    }

    /**
     * Test retrieving non-existent blob throws exception.
     */
    public function test_retrieve_non_existent_blob_throws_exception(): void
    {
        $this->expectException(\Exception::class);
        $this->expectExceptionMessage('Blob not found');
        
        $this->blobService->retrieve('non-existent-id');
    }

    /**
     * Test deleting a blob.
     */
    public function test_delete_blob(): void
    {
        $content = 'This is test content';
        $blob = $this->blobService->store($content, null, 'test.txt', 'text/plain');
        
        $result = $this->blobService->delete($blob->id);
        
        $this->assertTrue($result);
        $this->assertNull(Blob::find($blob->id));
    }

    /**
     * Test getting blob metadata.
     */
    public function test_get_blob_metadata(): void
    {
        $content = 'This is test content';
        $blob = $this->blobService->store($content, null, 'test.txt', 'text/plain');
        
        $metadata = $this->blobService->getMetadata($blob->id);
        
        $this->assertInstanceOf(Blob::class, $metadata);
        $this->assertEquals($blob->id, $metadata->id);
        $this->assertEquals($blob->original_filename, $metadata->original_filename);
        $this->assertEquals($blob->size_bytes, $metadata->size_bytes);
        $this->assertEquals($blob->mime_type, $metadata->mime_type);
        $this->assertEquals($blob->storage_backend, $metadata->storage_backend);
    }

    /**
     * Test listing blobs with pagination.
     */
    public function test_list_blobs(): void
    {
        // Create multiple blobs
        for ($i = 1; $i <= 5; $i++) {
            $content = "Content $i";
            $this->blobService->store(
                $content,
                null,
                "test$i.txt",
                'text/plain'
            );
        }
        
        $result = $this->blobService->listBlobs(1, 3);
        
        $this->assertIsArray($result);
        $this->assertArrayHasKey('data', $result);
        $this->assertArrayHasKey('pagination', $result);
        $this->assertEquals(5, $result['pagination']['total']);
        $this->assertEquals(3, $result['pagination']['per_page']);
        $this->assertCount(3, $result['data']);
    }

    /**
     * Test getting storage statistics.
     */
    public function test_get_storage_stats(): void
    {
        // Create some blobs
        $content1 = 'Content 1';
        $content2 = 'Content 2';
        $this->blobService->store($content1, null, 'test1.txt', 'text/plain');
        $this->blobService->store($content2, null, 'test2.txt', 'text/plain');
        
        $stats = $this->blobService->getStorageStats();
        
        $this->assertIsArray($stats);
        $this->assertArrayHasKey('total_blobs', $stats);
        $this->assertArrayHasKey('total_size_bytes', $stats);
        $this->assertArrayHasKey('total_size_formatted', $stats);
        $this->assertArrayHasKey('backend_distribution', $stats);
        $this->assertEquals(2, $stats['total_blobs']);
        $this->assertGreaterThan(0, $stats['total_size_bytes']);
    }

    /**
     * Test verifying blob integrity.
     */
    public function test_verify_blob_integrity(): void
    {
        $content = 'This is test content';
        $blob = $this->blobService->store($content, null, 'test.txt', 'text/plain');
        
        $isValid = $this->blobService->verifyBlobIntegrity($blob);
        
        $this->assertTrue($isValid);
    }

    /**
     * Test duplicate blob detection.
     */
    public function test_duplicate_blob_detection(): void
    {
        $content = 'This is test content';
        $sizeBytes = strlen($content);
        
        // Store the same content twice
        $blob1 = $this->blobService->store($content, null, 'test1.txt', 'text/plain');
        $blob2 = $this->blobService->store($content, null, 'test2.txt', 'text/plain');
        
        // Should return the same blob due to duplicate detection
        $this->assertEquals($blob1->checksum_md5, $blob2->checksum_md5);
        $this->assertEquals($blob1->id, $blob2->id);
    }
}
