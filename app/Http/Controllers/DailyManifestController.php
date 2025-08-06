<?php

namespace App\Http\Controllers;

use App\Models\BookableService;
use App\Services\BookingPriceCalculator;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;

class DailyManifestController extends Controller
{
    use AuthorizesRequests;

    protected BookingPriceCalculator $priceCalculator;

    public function __construct(BookingPriceCalculator $priceCalculator)
    {
        $this->priceCalculator = $priceCalculator;
    }

    /**
     * [GET] /services/{service}/daily-manifest
     * Fetches available slots and dynamically priced tickets for a given day.
     */
    public function showPrev(Request $request, BookableService $service): JsonResponse
    {
        $this->authorize('view', $service);
        
        $validated = $request->validate([
            'date' => 'required|date_format:Y-m-d',
            // The frontend sends the user's current selection to get an accurate price breakdown.
            'tickets' => 'present|array',
            'tickets.*.tier_id' => 'required|integer',
            'tickets.*.quantity' => 'required|integer|min:0',
        ]);
        
        // --- Fetch Slots ---
        $slots = $service->availabilitySlots()
                         ->whereDate('start_time', $validated['date'])
                         ->get(['id', 'start_time', 'capacity', 'booked_count']);
        
        // --- Run Pricing Engine ---
        $priceBreakdown = $this->priceCalculator->calculate(
            $service,
            $validated['date'],
            $validated['tickets']
        );
        
        // --- Combine and Respond ---
        return response()->json([
            'date' => $validated['date'],
            'slots' => $slots,
            'pricing' => $priceBreakdown,
        ]);
    }

     /**
     * [GET] /services/{service}/daily-manifest
     * Fetches available slots and dynamically priced tickets for a given day.
     */
    public function show(Request $request, BookableService $service): JsonResponse
    {
        // Policy check is optional here since we are showing public data,
        // but good practice if services can be private.
        // $this->authorize('view', $service);
        
        $validated = $request->validate([
            'date' => 'required|date_format:Y-m-d',
        ]);
        
        // --- Fetch Available Slots ---
        $slots = $service->availabilitySlots()
                         ->whereDate('start_time', $validated['date'])
                         ->where('status', 'open')
                         // A simple calculation to show capacity left
                         ->selectRaw('id, start_time, (capacity - booked_count) as capacity_left')
                         ->get();
        
        // --- Get Dynamically Priced Tickets ---
        $pricedTiers = $this->priceCalculator->getPricedTicketTiersForDate(
            $service,
            $validated['date']
        );
        
        return response()->json([
            'date' => $validated['date'],
            'slots' => $slots,
            'ticket_tiers' => $pricedTiers,
        ]);
    }
}