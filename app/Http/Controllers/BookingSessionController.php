<?php

namespace App\Http\Controllers;

use App\Models\BookableService;
use App\Models\BookingIntent;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Http\JsonResponse;

class BookingSessionController extends Controller
{
    /**
     * Start a new booking session or retrieve an existing one.
     * This is the first endpoint the widget calls.
     */
    public function start(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'service_uuid' => 'required|uuid|exists:bookable_services,uuid',
            'session_id' => 'nullable|string|exists:booking_intents,session_id',
        ]);

        // If a valid session ID was provided, find and "touch" it to refresh its expiration.
        if ($validated['session_id']) {
            $intent = BookingIntent::where('session_id', $validated['session_id'])
                                   ->where('status', 'active')
                                   ->first();
            if ($intent) {
                $intent->touch(); // Updates the updated_at timestamp.
                return response()->json(['session_id' => $intent->session_id]);
            }
        }
        
        // If no valid session was found, create a new one.
        $service = BookableService::where('uuid', $validated['service_uuid'])->firstOrFail();

        $intent = BookingIntent::create([
            'session_id' => 'sess_' . Str::random(24),
            'tenant_id' => $service->tenant_id,
            'bookable_service_id' => $service->id,
            'status' => 'active',
            'expires_at' => now()->addMinutes(30), // Set an expiration time
        ]);

        return response()->json(['session_id' => $intent->session_id], 201);
    }

    /**
     * Get the current state of a booking session.
     * The iframe app calls this on load to restore the user's progress.
     */
    public function show(BookingIntent $intent): JsonResponse
    {
        // Route-model binding finds the intent by its session_id.
        // We can add a check to ensure it's not expired.
        if ($intent->status !== 'active' || $intent->expires_at->isPast()) {
            return response()->json(['message' => 'This booking session has expired.'], 410); // 410 Gone
        }
        
        // Eager-load the service details for the frontend.
        $intent->load('bookableService');

        return response()->json($intent);
    }
}