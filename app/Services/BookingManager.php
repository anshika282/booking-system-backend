<?php

namespace App\Services;

use App\Models\Booking;
use App\Models\Customers;
use Illuminate\Support\Str;
use App\Models\BookingIntent;
use App\Services\TenantManager;
use App\Models\AvailabilitySlot;
use App\Services\PaymentService;
use Illuminate\Support\Facades\DB;
use App\Services\BookingPriceCalculator;
use Illuminate\Pagination\LengthAwarePaginator;

class BookingManager
{
    protected PaymentService $paymentService;
    protected TenantManager $tenantManager;
    // We'll inject the TenantManager to get the current tenant context.
    public function __construct(TenantManager $tenantManager, PaymentService $paymentService)
    {
        $this->tenantManager = $tenantManager;
        $this->paymentService = $paymentService;
    }
    /**
     * Finalizes a booking from an intent, processes payment, and confirms the reservation.
     * This entire method is wrapped in a database transaction.
     *
     * @param BookingIntent $intent The booking intent to be finalized.
     * @param string $paymentToken A token/ID from the payment gateway.
     * @return Booking The newly created, permanent booking record.
     * @throws \Exception If any step fails (e.g., payment, capacity check).
     */
    public function finalizeBookingFromIntent(BookingIntent $intent, string $paymentToken): Booking
    {
        return DB::transaction(function () use ($intent, $paymentToken) {
            // Ensure this intent hasn't already been completed.
            if ($intent->status !== 'active') {
                throw new \Exception('This booking session has expired or is already completed.');
            }

             // --- SECURITY STEP 1: RE-CALCULATE AUTHORITATIVE PRICE ---
            // This ensures no client-side tampering or late price rule updates are missed.
            $priceCalculator = app(BookingPriceCalculator::class);
            
            $selections = [
                'tickets' => $intent->intent_data['tickets'],
                'add_ons' => $intent->intent_data['add_ons'],
                'coupon_code' => $intent->intent_data['coupon_code'] ?? null,
            ];
            
            $finalBreakdown = $priceCalculator->calculate(
                $intent->bookableService, 
                $intent->intent_data['date'], 
                $selections
            );
            
            \Log::info('Price breakdown for booking intent', ['session_id' => $intent->session_id, 'breakdown' => $finalBreakdown]);
            $authoritativeTotal = $finalBreakdown->finalTotal;

            // --- SECURITY STEP 2: AUDIT PRICE CHECK (CRITICAL) ---
            // Check that the amount paid by the user (stored in the intent total_amount)
            // is not significantly different from the authoritative total.
            // A tolerance (e.g., 0.01) is used for floating point safety.
            if (abs($authoritativeTotal - $intent->total_amount) > 0.01) {
                // LOG THIS AS A HIGH-RISK FRAUD ATTEMPT/CRITICAL BUG
                \Log::critical('FRAUD/PRICE-TAMPERING DETECTED', [
                    'session_id' => $intent->session_id,
                    'paid_amount' => $intent->total_amount,
                    'correct_amount' => $authoritativeTotal,
                ]);
                // Since payment already passed, this means a serious data flaw.
                // It's safer to *fail the booking* but retain the payment.
                // Throwing an exception prevents finalization but retains the transaction.
                throw new \Exception('Price discrepancy detected. Booking aborted.');
            }
            
            
            // --- Step 3: Update Intent/Booking with authoritative, re-validated data ---
            $intent->total_amount = $authoritativeTotal; // Update with the final price
            // --- Step 4: Lock and Verify Inventory ---
            $slot = AvailabilitySlot::where('id', $intent->intent_data['slot_id'])
                                    ->lockForUpdate() // CRITICAL: Prevents race conditions
                                    ->firstOrFail();

            $totalTickets = collect($intent->intent_data['tickets'])->sum('quantity');

            if (($slot->capacity - $slot->booked_count) < $totalTickets) {
                // If capacity was taken while user was checking out, fail the transaction.
                throw new \Exception('The selected time slot is no longer available.');
            }

            // --- Step 2: Process Payment (Simulated) ---
            // In a real application, this would interact with a Stripe/PayPal service.
            // If the payment fails, it will throw an exception and the transaction will be rolled back.
            $this->processPayment($intent->total_amount, $paymentToken);

            // --- NEW LOGIC for handling Guest vs. Registered Customer ---
            $visitorInfo = $intent->intent_data['visitor_info'] ?? null;
            $customerId = $intent->customer_id; // This will be set for logged-in users

            if (empty($customerId)) {
            if (isset($visitorInfo['is_guest']) && $visitorInfo['is_guest'] === true) {
                // GUEST CHECKOUT
                $guestCustomer = Customers::where('is_placeholder', true)->firstOrFail();
                $customerId = $guestCustomer->id;
            } elseif ($visitorInfo) {
                // NEW CUSTOMER REGISTRATION AT CHECKOUT
                // We have the verified name, email, and phone, so we create the customer now.
                $tenant = $intent->bookableService->tenant; // Get the tenant from the service
                $customer = $this->customerManager->findOrCreateCustomer($visitorInfo, $tenant);
                $customerId = $customer->id;
            } else {
                throw new \Exception('Cannot finalize booking: No customer information found.');
            }
        }

            // --- Step 3: Create the Permanent Booking Record ---
            $booking = Booking::create([
                'booking_reference' => 'BK-' . strtoupper(Str::random(10)),
                'tenant_id' => $intent->tenant_id,
                'bookable_service_id' => $intent->bookable_service_id,
                'customer_id' => $customerId,
                'total_amount' => $intent->total_amount,
                'status' => 'confirmed',
                // This is the permanent, auditable record of the transaction.
                'booking_data_snapshot' => $intent->intent_data,
            ]);

            // --- Step 4: Create Booking Add-on Records ---
            if (!empty($intent->intent_data['add_ons'])) {
                $addOnsToSync = [];
                foreach ($intent->intent_data['add_ons'] as $addOnData) {
                    // Prepare data for the pivot table
                    $addOnsToSync[$addOnData['add_on_id']] = [
                        'quantity' => $addOnData['quantity'],
                        'price_at_booking' => $addOnData['price_at_booking'],
                    ];
                }
                $booking->addons()->sync($addOnsToSync);
            }

            // --- Step 4.1: Handle Coupon Usage ---
            if (!empty($intent->intent_data['coupon_code'])) {
                // Find the coupon and atomically increment its usage count.
                // The `where` clause is a safeguard against race conditions.
                $coupon = Coupon::where('code', $intent->intent_data['coupon_code'])->first();
                if ($coupon) {
                    // This is an atomic "increment" operation. It's safe.
                    $coupon->increment('used_count');
                }
            }

            // --- Step 5: Update the Inventory ---
            $slot->increment('booked_count', $totalTickets);

            // --- Step 6: Mark the Intent as Completed ---
            $intent->update(['status' => 'completed']);
            
            // --- (Optional) Step 7: Dispatch Post-Booking Jobs ---
            // SendBookingConfirmationEmail::dispatch($booking);
            
            return $booking;
        });
    }

    /**
     * A placeholder for your payment gateway integration.
     * @throws \Exception if payment fails.
     */
    private function processPayment(float $amount, string $token): bool
    {
        // Real logic: e.g., Stripe::charges()->create([...]);
        // For now, we'll assume it always succeeds. If it failed, it would throw an exception.
        if ($token === 'fail_payment') {
            throw new \Exception('Payment processing failed.');
        }
        return true;
    }

    /**
     * Retrieves a paginated and filterable list of bookings for the current tenant.
     *
     * @param array $filters Associative array of filters.
     * @param int $perPage
     * @return LengthAwarePaginator
     */
    public function getBookings(array $filters = [], int $perPage = 15): LengthAwarePaginator
    {
        $tenantId = $this->tenantManager->getCurrentTenantId();

        // Start the query, always scoped to the current tenant for security.
        $query = Booking::query()->where('tenant_id', $tenantId);

        // Eager-load related data. This is CRITICAL for performance.
        // It prevents the "N+1 query problem" by fetching all related customer and
        // service data in a couple of efficient queries instead of one query per booking.
        $query->with(['customer', 'service']);

        // Apply Status Filter (e.g., show only 'confirmed' bookings)
        if (!empty($filters['status'])) {
            $query->where('status', $filters['status']);
        }

        // Apply Service Filter (e.g., show bookings only for "Galactic Jump Zone")
        if (!empty($filters['service_id'])) {
            $query->where('bookable_service_id', $filters['service_id']);
        }

        // Apply Date Filter (e.g., show all bookings for today)
        // This is a common and important filter for daily operations.
        // It checks against the 'start_time' within the JSON snapshot.
        if (!empty($filters['date'])) {
            // Note: Querying JSON columns can be slower. Ensure your database version
            // supports JSON indexing if this becomes a performance bottleneck.
            $query->whereJsonContains('booking_data_snapshot->date', $filters['date']);
        }
        
        // Search Filter (e.g., find a booking by customer name or reference)
        if (!empty($filters['search'])) {
            $searchTerm = $filters['search'];
            $query->where(function ($q) use ($searchTerm) {
                $q->where('booking_reference', 'LIKE', "%{$searchTerm}%")
                  ->orWhereHas('customer', function ($customerQuery) use ($searchTerm) {
                      $customerQuery->where('name', 'LIKE', "%{$searchTerm}%")
                                    ->orWhere('email', 'LIKE', "%{$searchTerm}%");
                  });
            });
        }

        // Order by the most recent bookings first.
        $query->orderBy('created_at', 'desc');

        return $query->paginate($perPage);
    }

    /**
     * Initiates the payment process and returns the payment gateway URL.
     * 
     * @param BookingIntent $intent The intent to process.
     * @return string The redirect URL to the payment gateway.
     */
    public function initiatePayment(BookingIntent $intent): string
    {
        // 1. Final checks before payment: amount, visitor info, etc.
        if ($intent->total_amount <= 0) {
            // For a free booking, bypass the gateway
            // TODO: Call finalizeBookingFromIntent directly
            throw new \Exception("Cannot initiate payment for zero amount.");
        }
        
        // 2. Delegate to the PaymentService
        return $this->paymentService->initiatePayment($intent);
    }
}