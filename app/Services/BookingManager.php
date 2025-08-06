<?php

namespace App\Services;

use App\Models\Booking;
use Illuminate\Support\Str;
use App\Models\BookingIntent;
use App\Services\TenantManager;
use App\Models\AvailabilitySlot;
use Illuminate\Support\Facades\DB;
use Illuminate\Pagination\LengthAwarePaginator;

class BookingManager
{
    // We'll inject the TenantManager to get the current tenant context.
    public function __construct(TenantManager $tenantManager)
    {
        $this->tenantManager = $tenantManager;
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
            // --- Step 1: Lock and Verify Inventory ---
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

            // --- Step 3: Create the Permanent Booking Record ---
            $booking = Booking::create([
                'booking_reference' => 'BK-' . strtoupper(Str::random(10)),
                'tenant_id' => $intent->tenant_id,
                'bookable_service_id' => $intent->bookable_service_id,
                'customer_id' => $intent->customer_id,
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
}