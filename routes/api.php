<?php

use App\Http\Controllers\Api\BlobController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| API Routes
|--------------------------------------------------------------------------
|
| Here is where you can register API routes for your application. These
| routes are loaded by the RouteServiceProvider and all of them will
| be assigned to the "api" middleware group. Make something great!
|
*/

// Health check endpoint (no authentication required)
Route::get('/health', function () {
    return response()->json([
        'status' => 'ok',
        'timestamp' => now()->toISOString(),
        'version' => config('app.version', '1.0.0'),
    ]);
});

// Authentication routes (no authentication required)
Route::prefix('v1/auth')->group(function () {
    Route::post('/login', [\App\Http\Controllers\Api\AuthController::class, 'login']);
    Route::post('/register', [\App\Http\Controllers\Api\AuthController::class, 'register']);
});

// Protected API routes (require authentication)
Route::middleware('auth:sanctum')->group(function () {
    // User profile routes
    Route::prefix('v1/user')->group(function () {
        Route::get('/profile', function (Request $request) {
            return response()->json([
                'success' => true,
                'data' => $request->user(),
            ]);
        });
        Route::post('/logout', [\App\Http\Controllers\Api\AuthController::class, 'logout']);
    });

    // Blob management routes
    Route::prefix('v1/blobs')->group(function () {
        // List blobs with pagination and filtering
        Route::get('/', [BlobController::class, 'index']);
        
        // Store new blob
        Route::post('/', [BlobController::class, 'store']);
        
        // Get storage statistics
        Route::get('/stats', [BlobController::class, 'stats']);
        
        // Retrieve blob content
        Route::get('/{id}', [BlobController::class, 'show']);
        
        // Get blob metadata only
        Route::get('/{id}/metadata', [BlobController::class, 'metadata']);
        
        // Delete blob
        Route::delete('/{id}', [BlobController::class, 'destroy']);
    });

    // Storage configuration routes would go here (future implementation)
    // Route::middleware('can:manage-storage')->prefix('v1/storage')->group(function () {
    //     // Storage configuration endpoints
    // });
});

// Fallback route for undefined API endpoints
Route::fallback(function () {
    return response()->json([
        'success' => false,
        'message' => 'API endpoint not found',
        'available_endpoints' => [
            'GET /health' => 'Health check',
            'POST /v1/auth/login' => 'User login',
            'POST /v1/auth/register' => 'User registration',
            'GET /v1/user/profile' => 'Get user profile (authenticated)',
            'POST /v1/user/logout' => 'User logout (authenticated)',
            'GET /v1/blobs' => 'List blobs (authenticated)',
            'POST /v1/blobs' => 'Store blob (authenticated)',
            'GET /v1/blobs/stats' => 'Get storage statistics (authenticated)',
            'GET /v1/blobs/{id}' => 'Retrieve blob (authenticated)',
            'GET /v1/blobs/{id}/metadata' => 'Get blob metadata (authenticated)',
            'DELETE /v1/blobs/{id}' => 'Delete blob (authenticated)',
        ],
    ], 404);
});