<?php

namespace Tests\Unit;

use App\Models\Blob;
use App\Models\BlobStorage;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class BlobTest extends TestCase
{
    use RefreshDatabase;

    /**
     * Test blob creation with required attributes.
     */
    public function test_blob_creation(): void
    {
        $blob = Blob::create([
            'id' => 'test-blob-id',
            'size_bytes' => 1024,
            'mime_type' => 'text/plain',
            'storage_backend' => 'database',
            'storage_path' => 'path/to/blob',
            'checksum_md5' => md5('test content'),
        ]);

        $this->assertInstanceOf(Blob::class, $blob);
        $this->assertEquals('test-blob-id', $blob->id);

        $this->assertEquals(1024, $blob->size_bytes);
        $this->assertEquals('text/plain', $blob->mime_type);
        $this->assertEquals('database', $blob->storage_backend);
        $this->assertEquals('path/to/blob', $blob->storage_path);
        $this->assertEquals(md5('test content'), $blob->checksum_md5);
    }

    /**
     * Test blob relationship with blob data.
     */
    public function test_blob_has_one_blob_data(): void
    {
        $blob = Blob::create([
            'id' => 'test-blob-id',
            'size_bytes' => 1024,
            'mime_type' => 'text/plain',
            'storage_backend' => 'database',
            'storage_path' => 'path/to/blob',
            'checksum_md5' => md5('test content'),
        ]);

        $blobData = BlobStorage::create([
            'blob_id' => $blob->id,
            'data' => base64_encode('test content'),
        ]);

        $this->assertInstanceOf(BlobStorage::class, $blob->blobStorage);
        $this->assertEquals($blobData->id, $blob->blobStorage->id);
    }

    /**
     * Test uses database storage method.
     */
    public function test_uses_database_storage(): void
    {
        $databaseBlob = Blob::create([
            'id' => 'db-blob-id',
            'size_bytes' => 1024,
            'mime_type' => 'text/plain',
            'storage_backend' => 'database',
            'storage_path' => 'path/to/blob',
            'checksum_md5' => md5('test content'),
        ]);

        $localBlob = Blob::create([
            'id' => 'local-blob-id',
            'size_bytes' => 1024,
            'mime_type' => 'text/plain',
            'storage_backend' => 'local',
            'storage_path' => 'path/to/blob',
            'checksum_md5' => md5('test content'),
        ]);

        $this->assertTrue($databaseBlob->usesDatabaseStorage());
        $this->assertFalse($localBlob->usesDatabaseStorage());
    }

    /**
     * Test formatted size attribute.
     */
    public function test_formatted_size_attribute(): void
    {
        $blob = Blob::create([
            'id' => 'test-blob-id',
            'size_bytes' => 1024,
            'mime_type' => 'text/plain',
            'storage_backend' => 'database',
            'storage_path' => 'path/to/blob',
            'checksum_md5' => md5('test content'),
        ]);

        $this->assertEquals('1.00 KB', $blob->formatted_size);

        $blob->size_bytes = 1048576; // 1MB
        $this->assertEquals('1.00 MB', $blob->formatted_size);

        $blob->size_bytes = 1073741824; // 1GB
        $this->assertEquals('1.00 GB', $blob->formatted_size);
    }

    /**
     * Test blob primary key configuration.
     */
    public function test_blob_primary_key_configuration(): void
    {
        $blob = new Blob();
        
        $this->assertEquals('id', $blob->getKeyName());
        $this->assertEquals('string', $blob->getKeyType());
        $this->assertFalse($blob->getIncrementing());
    }

    /**
     * Test blob fillable attributes.
     */
    public function test_blob_fillable_attributes(): void
    {
        $blob = new Blob();
        $expectedFillable = [
            'id',
            'size_bytes',
            'mime_type',
            'storage_backend',
            'storage_path',
            'checksum_md5',
        ];

        $this->assertEquals($expectedFillable, $blob->getFillable());
    }
}
