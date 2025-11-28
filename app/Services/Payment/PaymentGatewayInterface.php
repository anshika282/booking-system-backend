<?php

namespace App\Services\Payment;

use App\Models\BookingIntent;

interface PaymentGatewayInterface
{
    /**
     * Initiates a payment request with the specific gateway API.
     * The implementation must return a URL for the client to redirect to.
     *
     * @param BookingIntent $intent The intent to be paid for.
     * @return string The redirect URL to the payment gateway.
     */
    public function initiatePayment(BookingIntent $intent): string;

    /**
     * Verifies the status of a payment given a transaction ID.
     * This is typically called by a secure backend webhook or final redirect.
     *
     * @param string $transactionId The unique transaction ID from the gateway.
     * @return bool True if payment is successful, false otherwise.
     */
    public function verifyPayment(string $transactionId): bool;
}