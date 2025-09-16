<?php

namespace App\Http\Controllers;

use App\Models\Tenant;
use Illuminate\Support\Str;
use Illuminate\Http\Request;
use App\Models\BookingIntent;
use App\Models\BookableService;
use App\Services\CustomerManager;
use Illuminate\Http\JsonResponse;
use App\Services\BookingPriceCalculator;
use App\Http\Requests\StoreVisitorInfoRequest;
use Illuminate\Database\Eloquent\ModelNotFoundException;

class BookingIntentController extends Controller
{
    protected BookingPriceCalculator $priceCalculator;
    protected CustomerManager $customerManager;

    public function __construct(BookingPriceCalculator $priceCalculator, CustomerManager $customerManager)
    {
        $this->priceCalculator = $priceCalculator;
        $this->customerManager = $customerManager;
    }

    public function calculateAndPersist(Request $request, BookableService $service)
    {
        $validated = $request->validate([
            'date' => 'required|date_format:Y-m-d',
            'slot_id' => 'required|integer|exists:availability_slots,id',
            'tickets' => 'present|array',
            'tickets.*.tier_id' => 'required|integer|exists:ticket_tiers,id',
            'tickets.*.quantity' => 'required|integer|min:0',
            'add_ons' => 'present|array',
            'add_ons.*.add_on_id' => 'required|integer|exists:addons,id',
            'add_ons.*.quantity' => 'required|integer|min:0',
            'coupon_code' => 'nullable|string',
            'session_id' => 'required|string|exists:booking_intents,session_id',
        ]);
        
        // Run the pricing engine first to get the calculated line items.
        $breakdown = $this->priceCalculator->calculate($service, $validated['date'], $validated);

        // --- BUILD THE DETAILED SNAPSHOT ---
        
        // Snapshot the selected add-ons with their price at this moment.
        $addOnsSnapshot = [];
        if (!empty($validated['add_ons'])) {
            $addOnIds = array_column($validated['add_ons'], 'add_on_id');
            $addOnModels = $service->addons()->whereIn('id', $addOnIds)->get()->keyBy('id');
            foreach($validated['add_ons'] as $selection) {
                $addOn = $addOnModels->get($selection['add_on_id']);
                if ($addOn) {
                    $addOnsSnapshot[] = [
                        'add_on_id' => $addOn->id,
                        'name' => $addOn->name,
                        'quantity' => $selection['quantity'],
                        // Store the price, which is 0 if it's an included add-on.
                        'price_at_booking' => $addOn->is_included_in_ticket ? 0 : $addOn->price,
                    ];
                }
            }
        }
        
        // The $breakdown->lineItems already contains the full snapshot of the tickets
        // with their final, rule-adjusted prices.
        
        $intentDataSnapshot = [
            'date' => $validated['date'],
            'slot_id' => $validated['slot_id'],
            'tickets' => $breakdown->lineItems->toArray(),
            'add_ons' => $addOnsSnapshot,
            'coupon_code' => $validated['coupon_code'] ?? null,
            // We can also store the applied discounts for display on the resume page
            'applied_discounts' => $breakdown->appliedDiscounts->toArray(),
        ];

        $intent = null;
        if (!empty($validated['session_id'])) {
            // If a session ID was provided, find that specific intent.
            $intent = BookingIntent::where('session_id', $validated['session_id'])->first();
        }
        
         if ($intent) {
            // --- UPDATE EXISTING INTENT ---
            $intent->update([
                // We only update the fields that change.
                'intent_data' => $intentDataSnapshot,
                'subtotal_amount' => $breakdown->adjustedSubtotal,
                'discounts_amount' => $breakdown->appliedDiscounts->sum('amount'),
                'addons_amount' => $breakdown->addOnsTotal,
                'total_amount' => $breakdown->finalTotal,
                'expires_at' => now()->addMinutes(15), // Refresh the expiration timer
            ]);
        } else {
            // --- CREATE NEW INTENT ---
            // If no valid session ID was found, create a new one.
            $intent = BookingIntent::create([
                'session_id' => 'sess_' . Str::random(20),
                'tenant_id' => $service->tenant_id,
                'bookable_service_id' => $service->id,
                'intent_data' => $intentDataSnapshot,
                'subtotal_amount' => $breakdown->adjustedSubtotal,
                'discounts_amount' => $breakdown->appliedDiscounts->sum('amount'),
                'addons_amount' => $breakdown->addOnsTotal,
                'total_amount' => $breakdown->finalTotal,
                'expires_at' => now()->addMinutes(15),
                'status' => 'active',
            ]);
        }

        return response()->json([
            'session_id' => $intent->session_id,
            'expires_at' => $intent->expires_at->toIso8601String(),
            'price_breakdown' => $breakdown->toArray(),
        ]);
    }

    /**
     * [PUT] /booking-intents/{intent:session_id}/visitor-info
     * Associates customer details with a booking intent.
     */
    public function storeVisitorInfo(StoreVisitorInfoRequest $request, BookingIntent $intent): JsonResponse
    {
        try {
                // Find or create the customer record
                $customer = $this->customerManager->findOrCreateCustomer($request->validated());

                // Link the customer to the intent
                $intent->customer_id = $customer->id;
                $intent->save();

                // In a real app, you would generate and return a payment intent secret here.
                return response()->json(['message' => 'Customer details saved successfully.']);
        
        } catch (ModelNotFoundException $e) {
            // Handle case where tenant or intent is not found
            return response()->json(['error' => 'Tenant or BookingIntent not found.'], 404);
        } catch (\Exception $e) {
            // Catch any other exceptions
            return response()->json(['error' => 'An error occurred. Please try again later.', 'details' => $e->getMessage()], 500);
        }
    }

     /**
     * --- NEW METHOD ---
     * Starts a new booking session or retrieves an existing one.
     * This is the very first API call the widget makes.
     */
    public function startOrResume(Request $request): JsonResponse
    {
         \Log::info('session_id: ' . ($validated['session_id'] ?? 'none'));
        $validated = $request->validate([
            'service_uuid' => 'required|uuid|exists:bookable_services,uuid',
            'session_id' => 'nullable|string|exists:booking_intents,session_id',
        ]);
        \Log::info('session_id: ' . ($validated['session_id'] ?? 'none'));

        // If a valid, active session ID was provided, "touch" it to refresh its expiration and return it.
        if (isset($validated['session_id'])) {
            $intent = BookingIntent::where('session_id', $validated['session_id'])
                                   ->where('status', 'active')
                                   ->where('expires_at', '>', now())
                                   ->first();
            if ($intent) {
                $intent->update(['expires_at' => now()->addMinutes(30)]); // Refresh expiry
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
            'expires_at' => now()->addMinutes(30),
        ]);

        return response()->json(['session_id' => $intent->session_id], 201);
    }

    
    /**
     * --- NEW METHOD ---
     * Gets the current state of a booking intent.
     * The iframe app calls this on load to restore the user's progress.
     */
    public function show(BookingIntent $intent): JsonResponse
    {
        \Log::info('Showing booking intent: ' . $intent->session_id);

        // Route-model binding finds the intent by its session_id.
        if ($intent->status !== 'active' || ($intent->expires_at && $intent->expires_at->isPast())) {
            return response()->json(['message' => 'This booking session has expired.'], 410); // 410 Gone
        }
        
        // Eager-load the necessary data for the frontend widget.
         $intent->load('bookableService.tenant', 'bookableService.ticketTiers', 'bookableService.addons');

        return response()->json($intent);
    }

}