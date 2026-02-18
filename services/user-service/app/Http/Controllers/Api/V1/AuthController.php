<?php

namespace App\Http\Controllers\Api\V1;

use App\Services\RabbitMQService;
use App\Events\UserRegisteredEvent;
use App\Events\UserLoggedInEvent;
use App\Http\Controllers\Controller;
use App\Http\Requests\LoginRequest;
use App\Http\Requests\RegisterRequest;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Hash;
use Tymon\JWTAuth\Facades\JWTAuth;

class AuthController extends Controller
{
    /**
     * Register a new user
     */
    public function register(RegisterRequest $request): JsonResponse
    {
        try {
            // Create user
            $user = User::create([
                'name' => $request->name,
                'email' => $request->email,
                'password' => Hash::make($request->password),
                'phone' => $request->phone,
            ]);

            // Generate JWT token
            $token = JWTAuth::fromUser($user);

            // Publish event to RabbitMQ
            try {
                $event = new UserRegisteredEvent(
                    user: $user,
                    timestamp: now()->toIso8601String()
                );

                $rabbitMQ = new RabbitMQService();
                $rabbitMQ->publish(
                    exchange: 'user_events',
                    routingKey: 'user.registered',
                    message: $event->toJson()
                );
            } catch (\Exception $e) {
                // Log error but don't fail registration
                \Log::error('Failed to publish user.registered event: ' . $e->getMessage());
            }

            return response()->json([
                'message' => 'User registered successfully',
                'user' => [
                    'id' => $user->id,
                    'name' => $user->name,
                    'email' => $user->email,
                    'phone' => $user->phone,
                    'role' => $user->role,
                    'status' => $user->status,
                ],
                'access_token' => $token,
                'token_type' => 'bearer',
            ], 201);

        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Registration failed',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Login user
     */
    public function login(LoginRequest $request): JsonResponse
    {
        $credentials = $request->only('email', 'password');

        try {
            if (!$token = JWTAuth::attempt($credentials)) {
                return response()->json([
                    'message' => 'Invalid credentials',
                ], 401);
            }

            // Get authenticated user
            $user = JWTAuth::user();

            // Check if user is active
            if (!$user->isActive()) {
                JWTAuth::invalidate($token);

                return response()->json([
                    'message' => 'Your account is not active',
                ], 403);
            }

            // Publish event to RabbitMQ
            try {
                $event = new UserLoggedInEvent(
                    user: $user,
                    ipAddress: $request->ip(),
                    userAgent: $request->userAgent() ?? 'Unknown',
                    timestamp: now()->toIso8601String()
                );

                $rabbitMQ = new RabbitMQService();
                $rabbitMQ->publish(
                    exchange: 'user_events',
                    routingKey: 'user.logged_in',
                    message: $event->toJson()
                );
            } catch (\Exception $e) {
                // Log error but don't fail login
                \Log::error('Failed to publish user.logged_in event: ' . $e->getMessage());
            }

            return response()->json([
                'message' => 'Login successful',
                'user' => [
                    'id' => $user->id,
                    'name' => $user->name,
                    'email' => $user->email,
                    'phone' => $user->phone,
                    'role' => $user->role,
                    'status' => $user->status,
                ],
                'access_token' => $token,
                'token_type' => 'bearer',
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Login failed',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Get authenticated user profile
     */
    public function profile(): JsonResponse
    {
        try {
            $user = JWTAuth::parseToken()->authenticate();

            if (!$user) {
                return response()->json([
                    'message' => 'User not found',
                ], 404);
            }

            return response()->json([
                'user' => [
                    'id' => $user->id,
                    'name' => $user->name,
                    'email' => $user->email,
                    'phone' => $user->phone,
                    'role' => $user->role,
                    'status' => $user->status,
                    'created_at' => $user->created_at,
                ],
            ]);
        } catch (\Tymon\JWTAuth\Exceptions\TokenInvalidException $e) {
            return response()->json([
                'message' => 'Token is invalid',
            ], 401);
        } catch (\Tymon\JWTAuth\Exceptions\TokenExpiredException $e) {
            return response()->json([
                'message' => 'Token has expired',
            ], 401);
        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Could not get user',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Logout user
     */
    public function logout(): JsonResponse
    {
        try {
            JWTAuth::parseToken()->invalidate();

            return response()->json([
                'message' => 'Successfully logged out',
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Could not logout',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Refresh JWT token
     */
    public function refresh(): JsonResponse
    {
        try {
            $token = JWTAuth::parseToken()->refresh();

            return response()->json([
                'access_token' => $token,
                'token_type' => 'bearer',
            ]);
        } catch (\Tymon\JWTAuth\Exceptions\TokenInvalidException $e) {
            return response()->json([
                'message' => 'Token is invalid',
            ], 401);
        } catch (\Tymon\JWTAuth\Exceptions\TokenExpiredException $e) {
            return response()->json([
                'message' => 'Token has expired',
            ], 401);
        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Could not refresh token',
                'error' => $e->getMessage(),
            ], 500);
        }
    }
}
