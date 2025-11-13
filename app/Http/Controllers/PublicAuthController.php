<?php

namespace App\Http\Controllers;

use App\Models\Tenant;
use App\Models\Customers;
use Illuminate\Support\Str;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Tymon\JWTAuth\Facades\JWTAuth;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\Facades\Cache;

class PublicAuthController extends Controller
{
    /**
     * Generate and send an OTP to the user's phone number.
     */
    public function sendOtp(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'phone_number' => 'required|string|max:20',
        ]);

        // In a real application, you would integrate an SMS service here.
        // For our MVP, we'll generate an OTP and store it in the cache.
        // We will return the OTP in the response for easy testing.
        $otp = random_int(100000, 999999);
        $cacheKey = 'otp_' . $validated['phone_number'];
        Cache::put($cacheKey, $otp, now()->addMinutes(5)); // OTP is valid for 5 minutes

        // SIMULATED SMS: For testing, we return the OTP in the response.
        // In production, you would remove this and use an SMS service.
        return response()->json([
            'message' => 'OTP sent successfully.',
            'testing_otp' => $otp, // REMOVE THIS LINE IN PRODUCTION
        ]);
    }

     /**
     * Verify the OTP. If the user exists, log them in.
     * If not, simply confirm that the phone number is now verified for this session.
     */
    public function verifyOtp(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'phone_number' => 'required|string|max:20',
            'otp' => 'required|string|digits:6',
            'tenant_uuid' => 'required|uuid|exists:tenants,uuid',
        ]);

        $cacheKey = 'otp_' . $validated['phone_number'];
        if (Cache::get($cacheKey) != $validated['otp']) {
            return response()->json(['message' => 'The OTP is invalid or has expired.'], 401);
        }
        Cache::forget($cacheKey);

        $tenant = Tenant::where('uuid', $validated['tenant_uuid'])->firstOrFail();
        $customer = Customers::where('phone_number', $validated['phone_number'])
                             ->first();

        if ($customer) {
            // --- EXISTING CUSTOMER FLOW ---
           $token = Str::random(60);
            $customer->forceFill(['api_token' => hash('sha256', $token)])->save();

            return response()->json([
                'status' => 'found',
                'customer' => $customer,
                'token' => $token, // Return the plain-text token
            ]);
        }

        // --- NEW CUSTOMER FLOW ---
        // The OTP was correct, but the user doesn't exist.
        // Simply return a success message indicating the phone is verified.
        
        return response()->json([
            'status' => 'verified_new_user',
            'message' => 'Phone number verified successfully.',
            // 'verification_token' => $verificationToken,
        ],202);
    }

     /**
     * Create a new customer record after their phone number has been verified.
     */
    public function register(Request $request): JsonResponse
    {
        if (!$request->hasValidSignature()) {
            return response()->json(['message' => 'Invalid or expired verification token.'], 403);
        }

        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'email' => 'required|email|max:255',
            'phone_number' => 'required|string|max:20',
            'tenant_uuid' => 'required|uuid|exists:tenants,uuid',
        ]);
        
        if (Customers::where('phone_number', $validated['phone_number'])->exists()) {
            return response()->json(['message' => 'A customer with this phone number already exists.'], 409);
        }

        $customer = Customers::create([
            'tenant_id' => $tenant->id,
            'name' => $validated['name'],
            'email' => $validated['email'],
            'phone_number' => $validated['phone_number'],
            'is_placeholder' => false,
        ]);

        // --- Generate a token for the newly created customer ---
        $token = Str::random(60);
        $customer->forceFill(['api_token' => hash('sha256', $token)])->save();

        return response()->json([
            'status' => 'created',
            'customer' => $customer,
            'token' => $token,
        ], 201);
    }
}