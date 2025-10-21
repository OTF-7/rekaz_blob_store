<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Database\QueryException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Validation\Rules\Password;
use Illuminate\Validation\ValidationException;

/**
 * @OA\Tag(
 *     name="Authentication",
 *     description="User authentication operations"
 * )
 */
class AuthController extends Controller
{
    /**
     * @OA\Post(
     *     path="/api/v1/auth/login",
     *     summary="User login",
     *     description="Authenticate user and return access token",
     *     tags={"Authentication"},
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             required={"email", "password"},
     *             @OA\Property(property="email", type="string", format="email", example="omartaha.tech7@gmail.com"),
     *             @OA\Property(property="password", type="string", format="password", example="password123")
     *         )
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Login successful",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="message", type="string", example="Login successful"),
     *             @OA\Property(
     *                 property="data",
     *                 type="object",
     *                 @OA\Property(property="access_token", type="string", example="1|abcdef123456..."),
     *                 @OA\Property(property="token_type", type="string", example="Bearer"),
     *                 @OA\Property(
     *                     property="user",
     *                     type="object",
     *                     @OA\Property(property="id", type="integer", example=1),
     *                     @OA\Property(property="name", type="string", example="Omar Taha"),
                     @OA\Property(property="email", type="string", example="omartaha.tech7@gmail.com"),
     *                     @OA\Property(property="created_at", type="string", format="date-time"),
     *                     @OA\Property(property="updated_at", type="string", format="date-time")
     *                 )
     *             )
     *         )
     *     ),
     *     @OA\Response(
     *         response=401,
     *         description="Invalid credentials",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=false),
     *             @OA\Property(property="message", type="string", example="Invalid credentials")
     *         )
     *     ),
     *     @OA\Response(
     *         response=422,
     *         description="Validation error",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=false),
     *             @OA\Property(property="message", type="string", example="Validation failed"),
     *             @OA\Property(property="errors", type="object")
     *         )
     *     )
     * )
     */
    public function login(Request $request): JsonResponse
    {
        // Rate limiting for login attempts
        $key = 'login.' . $request->ip();
        if (RateLimiter::tooManyAttempts($key, 5)) {
            $seconds = RateLimiter::availableIn($key);
            Log::warning('Rate limit exceeded for login', [
                'ip' => $request->ip(),
                'seconds_remaining' => $seconds,
            ]);
            
            return $this->errorResponse(
                'Too many login attempts. Please try again in ' . $seconds . ' seconds.',
                429
            );
        }

        try {
            $validatedData = $request->validate([
                'email' => 'required|email|max:255',
                'password' => 'required|string|min:1|max:255',
                'device_name' => 'nullable|string|max:255',
            ]);
        } catch (ValidationException $e) {
            return $this->errorResponse('Validation failed', 422, $e->errors());
        }

        $credentials = $request->only('email', 'password');
        $email = $validatedData['email'];
        $deviceName = $validatedData['device_name'] ?? 'Unknown Device';

        if (!Auth::attempt($credentials)) {
            RateLimiter::hit($key, 300); // 5 minutes lockout
            
            Log::warning('Failed login attempt', [
                'email' => $email,
                'ip' => $request->ip(),
                'user_agent' => $request->userAgent(),
                'timestamp' => now(),
            ]);

            return $this->errorResponse('Invalid credentials', 401);
        }

        RateLimiter::clear($key);
        
        /** @var User $user */
        $user = Auth::user();
        
        try {
            // Create token with specific abilities
            $token = $user->createToken($deviceName, ['*']);

            Log::info('User logged in successfully', [
                'user_id' => $user->id,
                'email' => $user->email,
                'device_name' => $deviceName,
                'ip' => $request->ip(),
                'timestamp' => now(),
            ]);

            return $this->successResponse([
                'access_token' => $token->plainTextToken,
                'token_type' => 'Bearer',
                'user' => $this->formatUserData($user),
            ], 'Login successful');
        } catch (\Exception $e) {
            Log::error('Token creation failed during login', [
                'user_id' => $user->id,
                'error' => $e->getMessage(),
                'ip' => $request->ip(),
            ]);
            
            return $this->errorResponse('Login failed. Please try again.', 500);
        }
    }

    /**
     * @OA\Post(
     *     path="/api/v1/auth/register",
     *     summary="User registration",
     *     description="Register a new user account",
     *     tags={"Authentication"},
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             required={"name", "email", "password", "password_confirmation"},
     *             @OA\Property(property="name", type="string", example="Omar Taha"),
             @OA\Property(property="email", type="string", format="email", example="omartaha.tech7@gmail.com"),
     *             @OA\Property(property="password", type="string", format="password", example="password123"),
     *             @OA\Property(property="password_confirmation", type="string", format="password", example="password123")
     *         )
     *     ),
     *     @OA\Response(
     *         response=201,
     *         description="Registration successful",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="message", type="string", example="Registration successful"),
     *             @OA\Property(
     *                 property="data",
     *                 type="object",
     *                 @OA\Property(property="access_token", type="string", example="1|abcdef123456..."),
     *                 @OA\Property(property="token_type", type="string", example="Bearer"),
     *                 @OA\Property(
     *                     property="user",
     *                     type="object",
     *                     @OA\Property(property="id", type="integer", example=2),
     *                     @OA\Property(property="name", type="string", example="Omar Taha"),
                     @OA\Property(property="email", type="string", example="omartaha.tech7@gmail.com"),
     *                     @OA\Property(property="created_at", type="string", format="date-time"),
     *                     @OA\Property(property="updated_at", type="string", format="date-time")
     *                 )
     *             )
     *         )
     *     ),
     *     @OA\Response(
     *         response=422,
     *         description="Validation error",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=false),
     *             @OA\Property(property="message", type="string", example="Validation failed"),
     *             @OA\Property(property="errors", type="object")
     *         )
     *     )
     * )
     */
    public function register(Request $request): JsonResponse
    {
        // Rate limiting for registration attempts
        $key = 'register.' . $request->ip();
        if (RateLimiter::tooManyAttempts($key, 3)) {
            $seconds = RateLimiter::availableIn($key);
            Log::warning('Rate limit exceeded for registration', [
                'ip' => $request->ip(),
                'seconds_remaining' => $seconds,
            ]);
            
            return $this->errorResponse(
                'Too many registration attempts. Please try again in ' . $seconds . ' seconds.',
                429
            );
        }

        try {
            $validatedData = $request->validate([
                'name' => 'required|string|max:255|regex:/^[\pL\s\-\']+$/u',
                'email' => 'required|string|email|max:255|unique:users,email',
                'password' => ['required', 'confirmed', Password::min(8)->letters()->mixedCase()->numbers()->symbols()],
                'device_name' => 'nullable|string|max:255',
            ]);
        } catch (ValidationException $e) {
            RateLimiter::hit($key, 600); // 10 minutes lockout for failed validation
            return $this->errorResponse('Validation failed', 422, $e->errors());
        }

        try {
            $user = User::create([
                'name' => trim($validatedData['name']),
                'email' => strtolower(trim($validatedData['email'])),
                'password' => Hash::make($validatedData['password']),
            ]);

            $deviceName = $validatedData['device_name'] ?? 'Unknown Device';
            $token = $user->createToken($deviceName, ['*']);

            RateLimiter::clear($key);

            Log::info('New user registered', [
                'user_id' => $user->id,
                'email' => $user->email,
                'name' => $user->name,
                'ip' => $request->ip(),
                'timestamp' => now(),
            ]);

            return $this->successResponse([
                'access_token' => $token->plainTextToken,
                'token_type' => 'Bearer',
                'user' => $this->formatUserData($user),
            ], 'Registration successful', 201);
        } catch (QueryException $e) {
            // Handle database constraint violations
            if ($e->errorInfo[1] === 1062) { // Duplicate entry
                Log::warning('Duplicate email registration attempt', [
                    'email' => $validatedData['email'],
                    'ip' => $request->ip(),
                ]);
                return $this->errorResponse('Email address is already registered', 422);
            }
            
            Log::error('Database error during registration', [
                'email' => $validatedData['email'],
                'error' => $e->getMessage(),
                'ip' => $request->ip(),
            ]);
            
            return $this->errorResponse('Registration failed due to database error', 500);
        } catch (\Exception $e) {
            Log::error('User registration failed', [
                'email' => $validatedData['email'],
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
                'ip' => $request->ip(),
            ]);

            return $this->errorResponse('Registration failed. Please try again.', 500);
        }
    }





    /**
     * @OA\Post(
     *     path="/api/v1/auth/logout",
     *     summary="User logout",
     *     description="Logout the authenticated user and revoke their access token",
     *     tags={"Authentication"},
     *     security={{"sanctum": {}}},
     *     @OA\Response(
     *         response=200,
     *         description="Logout successful",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="message", type="string", example="Logout successful")
     *         )
     *     ),
     *     @OA\Response(
     *         response=401,
     *         description="Unauthenticated",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=false),
     *             @OA\Property(property="message", type="string", example="Unauthenticated")
     *         )
     *     )
     * )
     */
    public function logout(Request $request): JsonResponse
    {
        try {
            // Get the current user's token
            $user = $request->user();
            
            if (!$user) {
                return $this->errorResponse('Unauthenticated', 401);
            }

            // Revoke the current access token
            $request->user()->currentAccessToken()->delete();

            Log::info('User logged out', [
                'user_id' => $user->id,
                'email' => $user->email,
                'ip' => $request->ip(),
                'timestamp' => now(),
            ]);

            return $this->successResponse(null, 'Logout successful');
        } catch (\Exception $e) {
            Log::error('Logout failed', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
                'ip' => $request->ip(),
            ]);

            return $this->errorResponse('Logout failed. Please try again.', 500);
        }
    }

    /**
     * Format user data for API responses
     *
     * @param User $user
     * @return array<string, mixed>
     */
    private function formatUserData(User $user): array
    {
        return [
            'id' => $user->id,
            'name' => $user->name,
            'email' => $user->email,
            'created_at' => $user->created_at,
            'updated_at' => $user->updated_at,
        ];
    }

    /**
     * Return a success response
     *
     * @param mixed $data
     * @param string $message
     * @param int $statusCode
     * @return JsonResponse
     */
    private function successResponse($data = null, string $message = 'Success', int $statusCode = 200): JsonResponse
    {
        $response = [
            'success' => true,
            'message' => $message,
        ];

        if ($data !== null) {
            $response['data'] = $data;
        }

        return response()->json($response, $statusCode);
    }

    /**
     * Return an error response
     *
     * @param string $message
     * @param int $statusCode
     * @param mixed $errors
     * @return JsonResponse
     */
    private function errorResponse(string $message, int $statusCode = 400, $errors = null): JsonResponse
    {
        $response = [
            'success' => false,
            'message' => $message,
        ];

        if ($errors !== null) {
            $response['errors'] = $errors;
        }

        return response()->json($response, $statusCode);
    }
}