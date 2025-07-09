<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\AuthController;

Route::prefix('auth')->group(function () {
    Route::post('/register', [AuthController::class, 'register']);
    Route::post('/login', [AuthController::class, 'login']);
    Route::post('/logout', [AuthController::class, 'logout']);
    Route::post('/refresh', [AuthController::class, 'refresh']);
    
});

Route::middleware(['auth:api', 'tenant.check'])->group(function () {

    // The /me route now also benefits from the tenant check
    Route::get('/me', [AuthController::class, 'me']);
    
    // --- ADD YOUR FUTURE TENANT-SCOPED ROUTES HERE ---
    // For example, based on your PRD:
    
    // Route::get('/services', [ServiceController::class, 'index']);
    // Route::get('/bookings', [BookingController::class, 'index']);
    // Route::get('/analytics/dashboard', [AnalyticsController::class, 'dashboard']);
});