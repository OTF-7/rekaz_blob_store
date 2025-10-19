<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\BlobService;
use Exception;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;

/**
 * @OA\Tag(
 *     name="Blobs",
 *     description="Blob storage and retrieval operations"
 * )
 */
class BlobController extends Controller
{
    private BlobService $blobService;

    public function __construct(BlobService $blobService)
    {
        $this->blobService = $blobService;
    }

    /**
     * @OA\Post(
     *     path="/v1/blobs",
     *     summary="Store a new blob",
     *     description="Store a blob of data using a unique identifier and Base64 encoded content",
     *     tags={"Blobs"},
     *     security={{"bearerAuth":{}}},
     *     @OA\RequestBody(
     *         required=true,
     *         description="JSON object containing blob ID and Base64 encoded data",
     *         @OA\MediaType(
     *             mediaType="application/json",
     *             @OA\Schema(
     *                 required={"id", "data"},
     *                 @OA\Property(
     *                     property="id",
     *                     type="string",
     *                     example="any_valid_string_or_identifier",
     *                     description="Unique identifier for the blob. Can be UUID, path, or any valid string",
     *                     maxLength=255
     *                 ),
     *                 @OA\Property(
     *                     property="data",
     *                     type="string",
     *                     example="SGVsbG8gU2ltcGxlIFN0b3JhZ2UgV29ybGQh",
     *                     description="Base64 encoded binary data. Must be valid Base64 format and non-empty after decoding",
     *                     pattern="^[A-Za-z0-9+\/]*={0,2}$"
     *                 )
     *             )
     *         )
     *     ),
     *     @OA\Response(
     *         response=201,
     *         description="Blob stored successfully",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="message", type="string", example="Blob stored successfully"),
     *             @OA\Property(
     *                 property="data",
     *                 type="object",
     *                 @OA\Property(property="id", type="string", example="any_valid_string_or_identifier"),
     *                 @OA\Property(property="size_bytes", type="integer", example=27),
     *                 @OA\Property(property="mime_type", type="string", example="application/octet-stream"),
     *                 @OA\Property(property="storage_backend", type="string", example="local"),
     *                 @OA\Property(property="created_at", type="string", format="date-time", example="2023-01-22T21:37:55Z")
     *             )
     *         )
     *     ),
     *     @OA\Response(
     *         response=400,
     *         description="Validation error - Invalid Base64 data or missing required fields",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=false),
     *             @OA\Property(property="message", type="string", example="The data field must be valid Base64 encoded data."),
     *             @OA\Property(
     *                 property="errors",
     *                 type="object",
     *                 @OA\Property(
     *                     property="data",
     *                     type="array",
     *                     @OA\Items(type="string", example="The data field must be valid Base64 encoded data.")
     *                 )
     *             )
     *         )
     *     ),
     *     @OA\Response(
     *         response=409,
     *         description="Conflict - Blob ID already exists",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=false),
     *             @OA\Property(property="message", type="string", example="Blob with ID 'any_valid_string_or_identifier' already exists")
     *         )
     *     )
     * )
     */
    public function store(Request $request): JsonResponse
    {
        // Determine if this is a file upload or Base64 content
        $hasFile = $request->hasFile('file');
        $hasBase64 = $request->has('id') && $request->has('data');
        $hasContent = $request->has('content');

        if (!$hasFile && !$hasBase64 && !$hasContent) {
            return response()->json([
                'success' => false,
                'message' => 'Either file upload, Base64 data (id + data), or content (content + filename + mime_type) is required',
            ], 422);
        }

        try {
            $user = $request->user();
            $preferredBackend = $request->input('storage_backend');

            if ($hasFile) {
                // Handle file upload
                $validator = Validator::make($request->all(), [
                    'file' => 'required|file|max:102400', // 100MB max
                    'storage_backend' => 'nullable|string|in:s3,database,local,ftp',
                ]);

                if ($validator->fails()) {
                    return response()->json([
                        'success' => false,
                        'message' => 'Validation failed',
                        'errors' => $validator->errors(),
                    ], 422);
                }

                $file = $request->file('file');
                $blob = $this->blobService->store(
                    $file,
                    null, // Auto-generate ID
                    null, // Auto-detect filename
                    null, // Auto-detect MIME type
                    $user,
                    $preferredBackend
                );
            } elseif ($hasBase64) {
                // Handle Base64 data with ID
                $validator = Validator::make($request->all(), [
                    'id' => 'required|string|max:255',
                    'data' => ['required', 'string', function ($attribute, $value, $fail) {
                        // Validate Base64 format
                        if (!preg_match('/^[A-Za-z0-9+\/]*={0,2}$/', $value)) {
                            $fail('The ' . $attribute . ' field must be valid Base64 encoded data.');
                            return;
                        }
                        
                        // Validate Base64 can be decoded
                        $decoded = base64_decode($value, true);
                        if ($decoded === false) {
                            $fail('The ' . $attribute . ' field must be valid Base64 encoded data.');
                            return;
                        }
                        
                        // Check if decoded data is not empty
                        if (empty($decoded)) {
                            $fail('The ' . $attribute . ' field cannot be empty after Base64 decoding.');
                        }
                    }],
                    'storage_backend' => 'nullable|string|in:s3,database,local,ftp',
                ]);

                if ($validator->fails()) {
                    return response()->json([
                        'success' => false,
                        'message' => 'Validation failed',
                        'errors' => $validator->errors(),
                    ], 422);
                }

                $base64Data = $request->input('data');
                $decodedContent = base64_decode($base64Data, true);
                $blobId = $request->input('id');
                
                $blob = $this->blobService->store(
                    $decodedContent,
                    $blobId,
                    $blobId, // Use ID as filename for Base64 data
                    'application/octet-stream', // Default MIME type, will be detected if possible
                    $user,
                    $preferredBackend
                );
            } else {
                // Handle raw content with metadata
                $validator = Validator::make($request->all(), [
                    'content' => 'required|string',
                    'filename' => 'required|string|max:255',
                    'mime_type' => 'required|string|max:100',
                    'storage_backend' => 'nullable|string|in:s3,database,local,ftp',
                ]);

                if ($validator->fails()) {
                    return response()->json([
                        'success' => false,
                        'message' => 'Validation failed',
                        'errors' => $validator->errors(),
                    ], 422);
                }

                $content = base64_decode($request->input('content'), true);
                if ($content === false) {
                    return response()->json([
                        'success' => false,
                        'message' => 'Invalid Base64 content',
                    ], 422);
                }

                $blob = $this->blobService->store(
                    $content,
                    null, // Auto-generate ID
                    $request->input('filename'),
                    $request->input('mime_type'),
                    $user,
                    $preferredBackend
                );
            }

            return response()->json([
                'success' => true,
                'message' => 'Blob stored successfully',
                'data' => [
                    'id' => $blob->id,
                    'original_filename' => $blob->original_filename,
                    'size_bytes' => $blob->size_bytes,
                    'size_formatted' => $blob->formatted_size,
                    'mime_type' => $blob->mime_type,
                    'storage_backend' => $blob->storage_backend,
                    'checksum_md5' => $blob->checksum_md5,
                    'created_at' => $blob->created_at,
                    'updated_at' => $blob->updated_at,
                ],
            ], 201);
        } catch (Exception $e) {
            Log::error('Blob storage failed', [
                'error' => $e->getMessage(),
                'user_id' => $request->user()?->id,
                'file_name' => $request->file('file')?->getClientOriginalName(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to store blob: ' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * @OA\Get(
     *     path="/v1/blobs/{id}",
     *     summary="Retrieve a blob by ID",
     *     tags={"Blobs"},
     *     security={{"bearerAuth":{}}},
     *     @OA\Parameter(
     *         name="id",
     *         in="path",
     *         required=true,
     *         description="Blob ID",
     *         @OA\Schema(type="string")
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Blob retrieved successfully",
     *         @OA\JsonContent(
     *             @OA\Property(property="id", type="string", example="any_valid_string_or_identifier"),
     *             @OA\Property(property="data", type="string", example="SGVsbG8gU2ltcGxlIFN0b3JhZ2UgV29ybGQh", description="Base64 encoded binary data"),
     *             @OA\Property(property="size", type="string", example="27", description="Size in bytes as string"),
     *             @OA\Property(property="created_at", type="string", format="date-time", example="2023-01-22T21:37:55Z", description="UTC timestamp")
     *         )
     *     ),
     *     @OA\Response(
     *         response=404,
     *         description="Blob not found",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=false),
     *             @OA\Property(property="message", type="string", example="Blob not found")
     *         )
     *     )
     * )
     */
    public function show(Request $request, string $id)
    {
        try {
            $result = $this->blobService->retrieve($id);
            $blob = $result['blob'];
            $content = $result['content'];
            
            // Check if download is requested
            if ($request->has('download') && $request->get('download') == '1') {
                return response($content)
                    ->header('Content-Type', $blob->mime_type)
                    ->header('Content-Disposition', 'attachment; filename="' . $blob->original_filename . '"')
                    ->header('Content-Length', $blob->size_bytes);
            }
            
            // Return JSON response by default
            return response()->json([
                'id' => $blob->id,
                'data' => base64_encode($content),
                'size' => (string) $blob->size_bytes,
                'created_at' => $blob->created_at->toISOString(),
            ]);
        } catch (Exception $e) {
            if ($e->getCode() === 404) {
                return response()->json([
                    'success' => false,
                    'message' => 'Blob not found',
                ], 404);
            }

            return response()->json([
                'success' => false,
                'message' => 'Failed to retrieve blob: ' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * @OA\Get(
     *     path="/v1/blobs/{id}/metadata",
     *     summary="Get blob metadata",
     *     description="Retrieve metadata for a blob without downloading the content",
     *     tags={"Blobs"},
     *     security={{"sanctum":{}}},
     *     @OA\Parameter(
     *         name="id",
     *         in="path",
     *         required=true,
     *         description="Blob ID",
     *         @OA\Schema(type="string", example="550e8400-e29b-41d4-a716-446655440000")
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Blob metadata",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(
     *                 property="data",
     *                 type="object",
     *                 @OA\Property(property="id", type="string", example="550e8400-e29b-41d4-a716-446655440000"),
     *                 @OA\Property(property="original_filename", type="string", example="document.pdf"),
     *                 @OA\Property(property="size_bytes", type="integer", example=1024000),
     *                 @OA\Property(property="size_formatted", type="string", example="1.00 MB"),
     *                 @OA\Property(property="mime_type", type="string", example="application/pdf"),
     *                 @OA\Property(property="storage_backend", type="string", example="s3"),
     *                 @OA\Property(property="checksum_md5", type="string", example="5d41402abc4b2a76b9719d911017c592"),
     *                 @OA\Property(property="created_at", type="string", format="date-time"),
     *                 @OA\Property(property="updated_at", type="string", format="date-time")
     *             )
     *         )
     *     ),
     *     @OA\Response(
     *         response=404,
     *         description="Blob not found",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=false),
     *             @OA\Property(property="message", type="string", example="Blob not found")
     *         )
     *     )
     * )
     */
    public function metadata(string $id): JsonResponse
    {
        $blob = $this->blobService->getMetadata($id);

        if (!$blob) {
            return response()->json([
                'success' => false,
                'message' => 'Blob not found',
            ], 404);
        }

        return response()->json([
            'success' => true,
            'data' => [
                'id' => $blob->id,
                'original_filename' => $blob->original_filename,
                'size_bytes' => $blob->size_bytes,
                'size_formatted' => $blob->formatted_size,
                'mime_type' => $blob->mime_type,
                'storage_backend' => $blob->storage_backend,
                'checksum_md5' => $blob->checksum_md5,
                'created_at' => $blob->created_at,
                'updated_at' => $blob->updated_at,
            ],
        ]);
    }

    /**
     * @OA\Get(
     *     path="/v1/blobs",
     *     summary="List blobs",
     *     description="Get a paginated list of blobs",
     *     tags={"Blobs"},
     *     security={{"sanctum":{}}},
     *     @OA\Parameter(
     *         name="page",
     *         in="query",
     *         required=false,
     *         description="Page number",
     *         @OA\Schema(type="integer", example=1)
     *     ),
     *     @OA\Parameter(
     *         name="per_page",
     *         in="query",
     *         required=false,
     *         description="Items per page (max 100)",
     *         @OA\Schema(type="integer", example=20)
     *     ),
     *     @OA\Parameter(
     *         name="mime_type",
     *         in="query",
     *         required=false,
     *         description="Filter by MIME type prefix",
     *         @OA\Schema(type="string", example="image/")
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="List of blobs",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(
     *                 property="data",
     *                 type="array",
     *                 @OA\Items(type="object")
     *             ),
     *             @OA\Property(
     *                 property="pagination",
     *                 type="object",
     *                 @OA\Property(property="current_page", type="integer", example=1),
     *                 @OA\Property(property="per_page", type="integer", example=20),
     *                 @OA\Property(property="total", type="integer", example=100),
     *                 @OA\Property(property="last_page", type="integer", example=5),
     *                 @OA\Property(property="has_more", type="boolean", example=true)
     *             )
     *         )
     *     )
     * )
     */
    public function index(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'page' => 'nullable|integer|min:1',
            'per_page' => 'nullable|integer|min:1|max:100',
            'mime_type' => 'nullable|string|max:100',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $validator->errors(),
            ], 400);
        }

        $page = $request->input('page', 1);
        $perPage = $request->input('per_page', 20);
        $mimeTypeFilter = $request->input('mime_type');

        try {
            $result = $this->blobService->listBlobs($page, $perPage, $mimeTypeFilter);

            return response()->json([
                'success' => true,
                'data' => [
                    'data' => array_map(function ($blob) {
                        return [
                            'id' => $blob->id,
                            'original_filename' => $blob->original_filename,
                            'size_bytes' => $blob->size_bytes,
                            'size_formatted' => $blob->formatted_size,
                            'mime_type' => $blob->mime_type,
                            'storage_backend' => $blob->storage_backend,
                            'created_at' => $blob->created_at,
                            'updated_at' => $blob->updated_at,
                        ];
                    }, $result['data']),
                    'total' => $result['pagination']['total'],
                    'per_page' => $result['pagination']['per_page'],
                    'current_page' => $result['pagination']['current_page'],
                    'last_page' => $result['pagination']['last_page'],
                ],
            ]);
        } catch (Exception $e) {
            Log::error('Failed to list blobs', [
                'error' => $e->getMessage(),
                'user_id' => $request->user()?->id,
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to retrieve blobs',
            ], 500);
        }
    }

    /**
     * @OA\Delete(
     *     path="/v1/blobs/{id}",
     *     summary="Delete a blob",
     *     description="Delete a blob and its associated data",
     *     tags={"Blobs"},
     *     security={{"sanctum":{}}},
     *     @OA\Parameter(
     *         name="id",
     *         in="path",
     *         required=true,
     *         description="Blob ID",
     *         @OA\Schema(type="string", example="550e8400-e29b-41d4-a716-446655440000")
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Blob deleted successfully",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="message", type="string", example="Blob deleted successfully")
     *         )
     *     ),
     *     @OA\Response(
     *         response=404,
     *         description="Blob not found",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=false),
     *             @OA\Property(property="message", type="string", example="Blob not found")
     *         )
     *     )
     * )
     */
    public function destroy(Request $request, string $id): JsonResponse
    {
        try {
            $deleted = $this->blobService->delete($id);

            if (!$deleted) {
                return response()->json([
                    'success' => false,
                    'message' => 'Blob not found',
                ], 404);
            }

            return response()->json([
                'success' => true,
                'message' => 'Blob deleted successfully',
            ]);
        } catch (Exception $e) {
            Log::error('Blob deletion failed', [
                'blob_id' => $id,
                'error' => $e->getMessage(),
                'user_id' => $request->user()?->id,
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to delete blob: ' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * @OA\Get(
     *     path="/v1/blobs/stats",
     *     summary="Get storage statistics",
     *     description="Get storage usage statistics",
     *     tags={"Blobs"},
     *     security={{"sanctum":{}}},
     *     @OA\Response(
     *         response=200,
     *         description="Storage statistics",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(
     *                 property="data",
     *                 type="object",
     *                 @OA\Property(property="total_blobs", type="integer", example=150),
     *                 @OA\Property(property="total_size_bytes", type="integer", example=1073741824),
     *                 @OA\Property(property="total_size_formatted", type="string", example="1.00 GB"),
     *                 @OA\Property(
     *                     property="by_backend",
     *                     type="object",
     *                     additionalProperties={
     *                         @OA\Property(property="blob_count", type="integer"),
     *                         @OA\Property(property="total_size", type="integer")
     *                     }
     *                 )
     *             )
     *         )
     *     )
     * )
     */
    public function stats(): JsonResponse
    {
        try {
            $stats = $this->blobService->getStorageStats();

            return response()->json([
                'success' => true,
                'data' => $stats,
            ]);
        } catch (Exception $e) {
            Log::error('Failed to get storage stats', [
                'error' => $e->getMessage(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to retrieve storage statistics',
            ], 500);
        }
    }
}