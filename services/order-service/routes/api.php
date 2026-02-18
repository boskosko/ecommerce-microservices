<?php

use App\Http\Controllers\Api\V1\OrderController;
use Illuminate\Support\Facades\Route;

// Health check endpoint
Route::get('/health', function () {
    try {
        \DB::connection()->getPdo();
        $dbStatus = 'connected';
    } catch (\Exception $e) {
        $dbStatus = 'disconnected';
    }

    return response()->json([
        'service' => 'order-service',
        'status' => 'healthy',
        'timestamp' => now()->toIso8601String(),
        'database' => $dbStatus,
    ]);
});

// API v1 routes
Route::prefix('v1')->group(function () {
    // Order routes
    Route::get('orders', [OrderController::class, 'index']);
    Route::post('orders', [OrderController::class, 'store']);
    Route::get('orders/{id}', [OrderController::class, 'show']);
    Route::patch('orders/{id}/status', [OrderController::class, 'updateStatus']);
    Route::delete('orders/{id}', [OrderController::class, 'cancel']);
});
