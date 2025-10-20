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
    Route::post('/logout', [\App\Http\Controllers\Api\AuthController::class, 'logout'])->middleware('auth:sanctum');
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
        Route::get('/{id}', [BlobController::class, 'show']); // Retrieve a blob (supports ?metadata_only=1 and ?download=1)

        // Delete blob
        Route::delete('/{id}', [BlobController::class, 'destroy']); // Delete a blob
    });

});

// Fallback route for undefined API endpoints
Route::fallback(function () {
    return response()->json([
        'success' => false,
        'message' => 'API endpoint not found',
        'available_endpoints' => [
            'GET /health' => 'Health check',
            // Authentication
            'POST /v1/auth/login' => 'User login',
            'POST /v1/auth/register' => 'User registration',
            'POST /v1/auth/logout' => 'User logout (authenticated)',
            'GET /v1/user/profile' => 'Get user profile (authenticated)',
            // Blob Management
            'GET /v1/blobs' => 'List blobs (authenticated)',
            'POST /v1/blobs' => 'Store blob (authenticated)',
            'GET /v1/blobs/stats' => 'Get storage statistics (authenticated)',
            'GET /v1/blobs/{id}' => 'Retrieve blob (authenticated)',
            'DELETE /v1/blobs/{id}' => 'Delete blob (authenticated)',
        ],
    ], 404);
});
