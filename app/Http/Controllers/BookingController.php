<?php

namespace App\Http\Controllers;

use App\Models\Booking;
use Illuminate\Http\Request;
use App\Models\BookingIntent;
use App\Services\BookingManager;
use Illuminate\Http\JsonResponse;
use App\Http\Requests\FinalizeBookingRequest;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use App\Http\Resources\BookingResource; // You would create this

class BookingController extends Controller
{
    use AuthorizesRequests;
    protected BookingManager $bookingManager;

    public function __construct(BookingManager $bookingManager)
    {
        $this->bookingManager = $bookingManager;
    }

    /**
     * [POST] /bookings/from-intent
     * Finalizes a booking from an intent after successful payment.
     */
    public function storeFromIntent(FinalizeBookingRequest $request): JsonResponse
    {
        $validated = $request->validated();
        $intent = BookingIntent::where('session_id', $validated['session_id'])->firstOrFail();
        
        // TODO: Add authorization policy check here to ensure the user owns this intent if they are logged in.
        
        try {
            $booking = $this->bookingManager->finalizeBookingFromIntent(
                $intent,
                $validated['payment_token']
            );
            
            $booking->load(['service', 'customer']);
            return (new BookingResource($booking))->response()->setStatusCode(201);

        } catch (\Exception $e) {
            // Handle errors like "slot not available" or "payment failed"
            return response()->json(['error' => $e->getMessage()], 422); // Unprocessable Entity
        }
    }

    /**
     * [GET] /bookings
     * Display a paginated list of bookings.
     */
    public function index(Request $request): JsonResponse
    {
        // Add a policy check for viewAny if needed, but tenant scoping in manager is the primary guard.
        
        $validated = $request->validate([
            'status' => 'sometimes|string|in:confirmed,cancelled,completed,no_show',
            'service_id' => 'sometimes|integer|exists:bookable_services,id',
            'date' => 'sometimes|date_format:Y-m-d',
            'search' => 'sometimes|string|max:100',
            'per_page' => 'sometimes|integer|min:1|max:100',
        ]);

        $perPage = $validated['per_page'] ?? 15;

        $bookings = $this->bookingManager->getBookings($validated, $perPage);
        
        return BookingResource::collection($bookings)->response();
    }

     /**
     * [GET] /bookings/{booking:booking_reference}
     * Display a specific booking.
     */
    public function show(Booking $booking): JsonResponse
    {
        // Authorize that the user's tenant owns this booking.
        $this->authorize('view', $booking);

        // Eager-load all necessary relationships for the detailed view.
        $booking->load(['customer', 'service', 'addons']);
        
        return (new BookingResource($booking))->response();
    }
}