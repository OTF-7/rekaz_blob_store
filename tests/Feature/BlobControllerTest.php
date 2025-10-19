<?php

namespace Tests\Feature;

use App\Models\Blob;
use App\Models\User;
use App\Services\BlobService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class BlobControllerTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    protected function setUp(): void
    {
        parent::setUp();
        
        $this->user = User::factory()->create();
    }

    /**
     * Test unauthenticated access is denied.
     */
    public function test_unauthenticated_access_denied(): void
    {
        $response = $this->getJson('/api/v1/blobs');
        $response->assertStatus(401);
    }

    /**
     * Test listing blobs with authentication.
     */
    public function test_list_blobs_authenticated(): void
    {
        Sanctum::actingAs($this->user);
        
        $response = $this->getJson('/api/v1/blobs');
        
        $response->assertStatus(200)
                 ->assertJsonStructure([
                     'success',
                     'data' => [
                         'data',
                         'total',
                         'per_page',
                         'current_page',
                         'last_page',
                     ],
                 ]);
    }

    /**
     * Test storing a blob via file upload.
     */
    public function test_store_blob_from_file(): void
    {
        Sanctum::actingAs($this->user);
        
        $file = UploadedFile::fake()->create('test.txt', 100, 'text/plain');
        
        $response = $this->postJson('/api/v1/blobs', [
            'file' => $file,
        ]);
        
        $response->assertStatus(201)
                 ->assertJsonStructure([
                     'success',
                     'message',
                     'data' => [
                         'id',
                         'size_bytes',
                         'mime_type',
                         'storage_backend',
                         'checksum_md5',
                         'created_at',
                     ],
                 ]);
        
        $this->assertDatabaseHas('blobs', [
            'mime_type' => 'text/plain',
        ]);
    }

    /**
     * Test storing a blob from raw content.
     */
    public function test_store_blob_from_content(): void
    {
        Sanctum::actingAs($this->user);
        
        $response = $this->postJson('/api/v1/blobs', [
            'content' => base64_encode('This is test content'),
            'filename' => 'test.txt',
            'mime_type' => 'text/plain',
        ]);
        
        $response->assertStatus(201)
                 ->assertJsonStructure([
                     'success',
                     'message',
                     'data' => [
                         'id',
                         'size_bytes',
                         'mime_type',
                         'storage_backend',
                         'checksum_md5',
                         'created_at',
                     ],
                 ]);
    }

    /**
     * Test retrieving a blob.
     */
    public function test_retrieve_blob(): void
    {
        Sanctum::actingAs($this->user);
        
        // First create a blob using the service to ensure proper storage
        $content = 'This is test content';
        $blob = app(BlobService::class)->store(
            $content,
            null,
            'test.txt',
            'text/plain',
            $this->user
        );
        
        $response = $this->getJson("/api/v1/blobs/{$blob->id}?download=1");
        
        $response->assertStatus(200);
        $this->assertStringStartsWith('text/plain', $response->headers->get('Content-Type'));
        $this->assertStringContainsString(
            'attachment; filename=',
            $response->headers->get('Content-Disposition')
        );
    }

    /**
     * Test getting blob metadata.
     */
    public function test_get_blob_metadata(): void
    {
        Sanctum::actingAs($this->user);
        
        $blob = Blob::create([
            'id' => 'test-blob-id',
            'size_bytes' => 1024,
            'mime_type' => 'text/plain',
            'storage_backend' => 'database',
            'storage_path' => '',
            'checksum_md5' => md5('test content'),
        ]);
        
        $response = $this->getJson("/api/v1/blobs/{$blob->id}?metadata_only=1");
        
        $response->assertStatus(200)
                 ->assertJsonStructure([
                     'success',
                     'data' => [
                         'id',
                         'size_bytes',
                         'mime_type',
                         'storage_backend',
                         'checksum_md5',
                         'created_at',
                         'updated_at',
                     ],
                 ]);
    }

    /**
     * Test deleting a blob.
     */
    public function test_delete_blob(): void
    {
        Sanctum::actingAs($this->user);
        
        $blob = Blob::create([
            'id' => 'test-blob-id',
            'size_bytes' => 1024,
            'mime_type' => 'text/plain',
            'storage_backend' => 'database',
            'storage_path' => '',
            'checksum_md5' => md5('test content'),
        ]);
        
        $response = $this->deleteJson("/api/v1/blobs/{$blob->id}");
        
        $response->assertStatus(200)
                 ->assertJson([
                     'success' => true,
                     'message' => 'Blob deleted successfully',
                 ]);
        
        $this->assertDatabaseMissing('blobs', ['id' => $blob->id]);
    }

    /**
     * Test getting storage statistics.
     */
    public function test_get_storage_stats(): void
    {
        Sanctum::actingAs($this->user);
        
        $response = $this->getJson('/api/v1/blobs/stats');
        
        $response->assertStatus(200)
                 ->assertJsonStructure([
                     'success',
                     'data' => [
                         'total_blobs',
                         'total_size_bytes',
                         'total_size_formatted',
                         'backend_distribution',
                     ],
                 ]);
    }

    /**
     * Test validation errors for invalid file upload.
     */
    public function test_store_blob_validation_errors(): void
    {
        Sanctum::actingAs($this->user);
        
        // Test without file or content - should return custom error message
        $response = $this->postJson('/api/v1/blobs', []);
        $response->assertStatus(422)
                 ->assertJson([
                     'success' => false,
                     'message' => 'Either file upload, Base64 data (id + data), or content (content + filename + mime_type) is required',
                 ]);
        
        // Test with invalid file size (over 100MB limit)
        $file = UploadedFile::fake()->create('test.txt', 102401, 'text/plain'); // Over 100MB
        $response = $this->postJson('/api/v1/blobs', ['file' => $file]);
        $response->assertStatus(422)
                 ->assertJsonValidationErrors(['file']);
    }

    /**
     * Test retrieving non-existent blob returns 404.
     */
    public function test_retrieve_non_existent_blob(): void
    {
        Sanctum::actingAs($this->user);
        
        $response = $this->getJson('/api/v1/blobs/non-existent-id');
        
        $response->assertStatus(404)
                 ->assertJson([
                     'success' => false,
                     'message' => 'Blob not found',
                 ]);
    }

    /**
     * Test deleting non-existent blob returns 404.
     */
    public function test_delete_non_existent_blob(): void
    {
        Sanctum::actingAs($this->user);
        
        $response = $this->deleteJson('/api/v1/blobs/non-existent-id');
        
        $response->assertStatus(404)
                 ->assertJson([
                     'success' => false,
                     'message' => 'Blob not found',
                 ]);
    }
}
