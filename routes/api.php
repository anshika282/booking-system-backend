<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\UserController;
use App\Http\Controllers\AddOnController;
use App\Http\Controllers\CouponController;
use App\Http\Controllers\BookingController;
use App\Http\Controllers\CustomerController;
use App\Http\Controllers\LocationController;
use App\Http\Controllers\TicketTierController;
use App\Http\Controllers\PricingRuleController;
use App\Http\Controllers\BookingIntentController;
use App\Http\Controllers\DailyManifestController;
use App\Http\Controllers\OperatingHourController;
use App\Http\Controllers\PublicBookingController;
use App\Http\Controllers\BookableServiceController;

Route::prefix('v1')->group(function () {
    Route::prefix('auth')->group(function () {
        Route::post('/register', [AuthController::class, 'register']);
        Route::post('/login', [AuthController::class, 'login']);
        Route::post('/logout', [AuthController::class, 'logout']);
        Route::post('/refresh', [AuthController::class, 'refresh']);
        Route::post('/reset-password', [AuthController::class, 'resetPassword']);
    });

    Route::middleware(['auth:api', 'tenant.check'])->group(function () {
        Route::get('/me', [AuthController::class, 'me']);
        
        // Phase 1a:Service Listing and Creation
        Route::get('/services', [BookableServiceController::class, 'index']);
        // Phase 1b: Create a new service
        Route::post('/services', [BookableServiceController::class, 'store']);
        // Phase 1c:Service Listing and Creation
        Route::get('/services/{service}', [BookableServiceController::class, 'show']);

        Route::delete('/services/{service}', [BookableServiceController::class, 'destroy']); 
        Route::post('/services/{service}/restore', [BookableServiceController::class, 'restore']);
        Route::patch('/services/{service}', [BookableServiceController::class, 'update']);

        // Operating Hours Routes (Phase 2)
        Route::prefix('services/{service}/operating-hours')->group(function () {
            Route::get('/', [OperatingHourController::class, 'index']);  // Get Operating Hours
            Route::put('/', [OperatingHourController::class, 'update']); // Update Operating Hours
        });

        // Ticket Tier Routes (Phase 3)
        Route::prefix('services/{service}/ticket-tiers')->group(function () {
            Route::get('/', [TicketTierController::class, 'index']);  
            Route::post('/', [TicketTierController::class, 'store']); 
            // The primary "reconciliation" endpoint for the main UI. Replaces the entire collection.
            Route::put('/', [TicketTierController::class, 'batchUpdate']);
            Route::get('/{ticketTier}', [TicketTierController::class, 'show']);
            Route::patch('/{ticketTier}', [TicketTierController::class, 'update']);
            Route::delete('/{ticketTier}', [TicketTierController::class, 'destroy']);
            
            Route::put('/reorder', [TicketTierController::class, 'reorder']);
        });

        Route::prefix('services/{service}/pricing')->group(function () {
            Route::get('/', [PricingRuleController::class, 'index']);  
            Route::post('/', [PricingRuleController::class, 'store']);
             Route::get('/{rule}', [PricingRuleController::class, 'show']);
             //Route::put('/{rule}', [PricingRuleController::class, 'update']);
             Route::patch('/reorder', [PricingRuleController::class, 'reorder']);
            Route::patch('/{rule}', [PricingRuleController::class, 'patch']);
            Route::delete('/{rule}', [PricingRuleController::class, 'destroy']);
             
        });

        Route::prefix('services/{service}/coupons')->group(function () {
            Route::get('/', [CouponController::class, 'index']);  
            Route::post('/', [CouponController::class, 'store']);
             Route::get('/{coupon}', [CouponController::class, 'show']);
            Route::put('/{coupon}', [CouponController::class, 'update']);
             Route::patch('/{coupon}', [CouponController::class, 'patchValidity']);
            Route::delete('/{coupon}', [CouponController::class, 'destroy']);
        });

        // Actions on the collection of add-ons for a service
        Route::prefix('services/{service}/add-ons')->group(function () {
            Route::get('/', [AddOnController::class, 'index']);
            Route::post('/', [AddOnController::class, 'store']);
            Route::get('/{addOn}', [AddOnController::class, 'show']);
            Route::patch('/{addOn}', [AddOnController::class, 'update']);
            Route::delete('/{addOn}', [AddOnController::class, 'destroy']);
        });

        
        Route::get('/bookings', [BookingController::class, 'index']);
        Route::get('/bookings/{booking:booking_reference}', [BookingController::class, 'show']);

        Route::get('/customers', [CustomerController::class, 'index']);
        Route::get('/customers/{customer}', [CustomerController::class, 'show']);

        Route::get('/users', [UserController::class, 'index'])->middleware('can:manage-team');
        Route::post('/users', [UserController::class, 'store'])->middleware('can:manage-team');

        // --- ADD YOUR FUTURE TENANT-SCOPED ROUTES HERE --
    });
    Route::prefix('locations')->group(function () {
           Route::get('/', [LocationController::class, 'index']);   
            Route::post('/', [LocationController::class, 'store']);
            Route::get('/{location}', [LocationController::class, 'show']);
            Route::put('/{location}', [LocationController::class, 'update']);
            Route::patch('/{location}', [LocationController::class, 'update']);
            Route::delete('/{location}', [LocationController::class, 'destroy']);
        });

    // --- PUBLIC-FACING ROUTES (NO AUTH MIDDLEWARE) ---
    Route::prefix('public')->group(function () {

        /**
         * Get the public details of a service for a specific tenant,
         * identified by the tenant's UUID.
         */

        Route::prefix('tenants/{tenant:uuid}')->scopeBindings()->group(function () {
            /**
             * Get the public details of a service for a specific tenant.
             */
            Route::get('/services/{service:uuid}', [PublicBookingController::class, 'showService']);

            /**
             * Get the daily availability and pricing for a specific service.
             * This route now correctly inherits the tenant scope.
             */
            Route::get('/services/{service:uuid}/daily-manifest', [DailyManifestController::class, 'show']);
            // --- NEW BOOKING SESSION ROUTES ---
        });
 
        Route::post('/booking-intents/start', [BookingIntentController::class, 'startOrResume']);
        Route::get('/booking-intents/{intent:session_id}', [BookingIntentController::class, 'show']);

        Route::post('/services/{service:uuid}/calculate-price', [BookingIntentController::class, 'calculateAndPersist']);
        // Route::get('/services/{service:uuid}/daily-manifest', [DailyManifestController::class, 'show']);

        Route::put('/booking-intents/{intent:session_id}/visitor-info', [BookingIntentController::class, 'storeVisitorInfo']);

        // Step 2: Finalize the booking after payment confirmation.
        Route::post('/bookings/from-intent', [BookingController::class, 'storeFromIntent']);

    });
});