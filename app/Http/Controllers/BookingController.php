<?php

namespace App\Http\Controllers;

use App\Models\Booking;
use Illuminate\Http\Request;
use App\Models\BookingIntent;
use App\Services\BookingManager;
use App\Services\PaymentService;
use Illuminate\Http\JsonResponse;
use App\Http\Requests\FinalizeBookingRequest;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use App\Http\Resources\BookingResource; // You would create this

class BookingController extends Controller
{
    use AuthorizesRequests;
    protected BookingManager $bookingManager;
     protected PaymentService $paymentService;

    public function __construct(BookingManager $bookingManager, PaymentService $paymentService)
    {
        $this->bookingManager = $bookingManager;
        $this->paymentService = $paymentService;
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

     /**
     * [POST] /public/bookings
     * Initiates the payment process for a booking intent and returns the redirect URL.
     */
    public function store(Request $request): JsonResponse // Repurposed store method
    {
        $validated = $request->validate([
            'session_id' => 'required|string|exists:booking_intents,session_id',
        ]);
        
        $intent = BookingIntent::where('session_id', $validated['session_id'])
                               ->where('status', 'active')
                               ->firstOrFail();

        try {
            $redirectUrl = $this->bookingManager->initiatePayment($intent);
            
            return response()->json([
                'message' => 'Payment initiated successfully.',
                'redirect_url' => $redirectUrl,
            ], 200);

        } catch (\Exception $e) {
            return response()->json(['error' => $e->getMessage()], 422); 
        }
    }

     /**
     * [ANY] /public/payment/phonepe/verify
     * Handles the redirect from PhonePe (Browser callback).
     * This simply confirms the payment on PhonePe and redirects the user to the frontend success/fail page.
     * 
     * @param Request $request Laravel request object
     */
    public function handlePhonePeCallback(Request $request)
    {
        \Log::info('PhonePe Callback Received', $request->all());
        // 1. Get the merchantTransactionId from the query/payload (PhonePe passes it back)
        $transactionId = $request->input('merchantTransactionId');

        // 2. Perform FINAL verification (S2S verification is more secure, but this is the user-facing response)
        // We call the payment service to confirm the payment on the gateway.
        
        
        // 3. Find the intent using the transaction ID (which is the session_id)
        $intent = BookingIntent::where('session_id', $transactionId)->first();
        // FIX: Revert the host origin logic to make the final redirect
        // We will default to the Vue App's base URL for simplicity.

        $finalSuccessUrl = config('app.frontend_url') . '/booking-flow/success';
        $finalFailUrl = config('app.frontend_url') . '/booking-flow/failure';
        try {
        // FIX: Pass the TENANT ID and the GATEWAY KEY for verification
            $isSuccess = $this->paymentService->verifyTransaction(
                $intent->tenant_id, // CRITICAL FIX: Pass Tenant ID
                'phonepe', 
                $transactionId
                );
        } catch (\Exception $e) {
            \Log::error('PhonePe Verification Error:', ['message' => $e->getMessage(), 'txId' => $transactionId]);
            // return redirect($failRedirect . '?msg=' . urlencode('Verification failed: ' . $e->getMessage()));
        }
        
        $vueAppBaseUrl = config('app.frontend_url');
        $successRoute = $vueAppBaseUrl . '/booking-flow/success';
        $failureRoute = $vueAppBaseUrl . '/booking-flow/failure';
        $bookingReference = null;
        $status = $isSuccess ? 'success' : 'failure';
        $errorMessage = $request->input('msg');
        if ($status === 'success' && $intent && $intent->status !== 'completed') {
            try {
                // Ensure the payment has not been finalized yet and then finalize it
                $booking = $this->bookingManager->finalizeBookingFromIntent($intent, $transactionId);

                // Redirect to the frontend success page (assuming this route exists)
                \Log::info('Booking finalized successfully for intent: ' . $intent->session_id);

                // return redirect($finalSuccessUrl); 
                $bookingReference = $booking->booking_reference;
                

            } catch (\Exception $e) {
                // Handle a failure during finalization (e.g., race condition took the slot)
                // return redirect(config('app.frontend_url') . '/booking-flow/error?msg=' . urlencode('Payment Success, but slot unavailable. Contact support.'));
                                // FIX: Use the failure route in the JavaScript response
                $status = 'error';
                // $msg = urlencode('Payment Success, but booking finalization failed. Contact support.');
                $errorMessage = 'Payment Processed, Booking Failed. Contact Support.';
                \Log::error('Finalization failure after successful payment: ' . $e->getMessage());

            }
        }
        
        // Redirect to the failure page
        // return redirect($finalFailUrl . '?msg=' . urlencode('Payment failed or was cancelled.'));
        return view('payment-redirect-bridge', [
        'status' => $status,
        'reference' => $bookingReference,
        'sessionId' => $transactionId,
        'errorMsg' => $errorMessage
    ]);
    }

    /**
     * [POST] /public/payment/phonepe/webhook
     * Handles the secure, server-to-server webhook from PhonePe.
     * This method is purely for reliability and should process the finalization without browser interaction.
     */
    public function handlePhonePeWebhook(Request $request)
    {
        // TODO: Implement webhook logic for production.
        // 1. Verify the X-VERIFY header to ensure the call is from PhonePe (CRITICAL SECURITY)
        // 2. Extract transactionId.
        // 3. Call $this->paymentService->verifyTransaction().
        // 4. If successful, call $this->bookingManager->finalizeBookingFromIntent().
        
        // For MVP, we can rely on the browser callback, but this is best practice.
        return response()->json(['status' => 'acknowledged']);
    }

      /**
     * [POST] /public/bookings/initiate-payment
     * Initiates the payment process for a booking intent and returns the redirect URL.
     * This replaces the problematic 'store' method repurposing.
     */
    public function initiatePayment(Request $request): JsonResponse // NEW/REPURPOSED METHOD NAME
    {
        // 1. Validation to get the session ID
        $validated = $request->validate([
            'session_id' => 'required|string|exists:booking_intents,session_id',
        ]);
        
        // 2. Find the active intent
        $intent = BookingIntent::where('session_id', $validated['session_id'])
                               ->where('status', 'active')
                               ->firstOrFail();
        \Log::info('intent: ' . $intent);
        // 3. Delegate to the BookingManager to initiate payment
        try {
            $redirectUrl = $this->bookingManager->initiatePayment($intent);
            \Log::info('Initiating payment for intent: ' . $intent->session_id);
            // 4. Return the URL for the frontend to redirect the user
            return response()->json([
                'message' => 'Payment initiated successfully.',
                'redirect_url' => $redirectUrl,
            ], 200);

        } catch (\Exception $e) {
            \Log::error('Payment initiation failed: ' . $e->getMessage());
            return response()->json(['error' => $e->getMessage() ?? 'An unknown payment error occurred.'], 422); 
        }
    }

    /**
     * [POST] /public/bookings/verify-payment-status
     * Called by Frontend after SDK closes. Forces a status check with Gateway.
     */
    public function verifyPaymentStatus(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'session_id' => 'required|string|exists:booking_intents,session_id',
        ]);

        $transactionId = $validated['session_id'];
        $intent = BookingIntent::where('session_id', $transactionId)->firstOrFail();

        // If already completed, return success immediately
        if ($intent->status === 'completed') {
            $booking = Booking::where('booking_intent_id', $intent->id)->first();
            return response()->json(['status' => 'success', 'booking_reference' => $booking->booking_reference]); // You can fetch real ref
        }

        try {
            // Force check with PhonePe
            $isSuccess = $this->paymentService->verifyTransaction(
                $intent->tenant_id,
                'phonepe',
                $transactionId
            );

            if ($isSuccess) {
                // Finalize the booking (create record, update inventory)
                $booking = $this->bookingManager->finalizeBookingFromIntent($intent, $transactionId);
                return response()->json([
                    'status' => 'success',
                    'booking_reference' => $booking->booking_reference
                ]);
            } else {
                return response()->json(['status' => 'failure', 'message' => 'Payment failed or pending.']);
            }
        } catch (\Exception $e) {
            \Log::error('Manual Verification Failed: ' . $e->getMessage());
            return response()->json(['status' => 'error', 'message' => $e->getMessage()], 422);
        }
    }
}