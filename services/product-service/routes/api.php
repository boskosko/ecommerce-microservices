<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Api\V1\ProductController;

// Health check endpoint
Route::get('/health', function () {
    try {
        \DB::connection()->getDatabase()->command(['ping' => 1]);
        $dbStatus = 'connected';
    } catch (\Exception $e) {
        $dbStatus = 'disconnected';
    }

    return response()->json([
        'service' => 'product-service',
        'status' => 'healthy',
        'timestamp' => now()->toIso8601String(),
        'database' => $dbStatus,
    ]);
});

// Product API routes
Route::prefix('v1')->group(function () {
    Route::apiResource('products', ProductController::class);
});
