<?php

namespace App\Services;

use App\Models\BookingIntent;
use App\Services\Payment\PaymentGatewayInterface;
use App\Services\Payment\PhonePeGateway;
use App\Models\TenantPaymentConfig;
use Exception;

/**
 * Acts as the Facade/Manager for all payment integrations.
 * This class ensures scalability by managing which concrete gateway is used.
 */
class PaymentService
{
    // A mapping of configuration strings to concrete implementation classes
    protected array $gateways = [
        'phonepe' => PhonePeGateway::class,
        // 'stripe' => StripeGateway::class, // Future Gateway
    ];

    /**
     * Dynamically resolves the correct payment gateway instance.
     *
     * @param string $gatewayKey The key of the desired gateway (e.g., 'phonepe').
     * @return PaymentGatewayInterface
     * @throws Exception
     */
    protected function resolveInitiationGateway(BookingIntent $intent): PaymentGatewayInterface
    {
         // 1. Find the default payment configuration for the tenant.
        // FIX: Use $intent->tenant_id (integer) for the database query.
        \Log::info('Resolving payment gateway for tenant ID: ' . $intent->tenant_id);
        
        $config = TenantPaymentConfig::where('tenant_id', $intent->tenant_id)
                                    ->where('is_default', true)
                                    ->first();

        // The log below will now show the eloquent model or null, confirming the query ran correctly.
        \Log::info('TenantPaymentConfig Found: ' . ($config ? $config->id : 'NULL')); 
        
        if (!$config) {
            throw new Exception('No default payment gateway configured for this tenant.');
        }

        $gatewayKey = $config->gateway_type;
        $credentials = $config->credentials;

        if (!isset($this->gateways[$gatewayKey])) {
            throw new Exception("Payment gateway '{$gatewayKey}' is recognized but not supported by the application code.");
        }

        $gatewayClass = $this->gateways[$gatewayKey];
        
        // 2. Instantiate the concrete gateway with its credentials
        return app($gatewayClass, ['credentials' => $credentials]);
    }

    /**
     * Initiates the payment process using the configured gateway for the intent.
     *
     * @param BookingIntent $intent
     * @return string Redirect URL
     * @throws Exception
     */
    public function initiatePayment(BookingIntent $intent): string
    {
       $gateway = $this->resolveInitiationGateway($intent);

        return $gateway->initiatePayment($intent);
    }

    /**
     * Verifies a payment transaction, crucial for webhooks and redirects.
     *
     * @param string $gatewayKey The gateway key.
     * @param string $transactionId The merchant transaction ID (session_id).
     * @return bool
     * @throws Exception
     */
    public function verifyTransaction(int $tenantId, string $gatewayKey, string $transactionId): bool
    {
        $gateway = $this->resolveVerificationGateway($tenantId, $gatewayKey);

        return $gateway->verifyPayment($transactionId);
    }

     /**
     * Resolves the correct payment gateway instance for VERIFICATION.
     * This is used for WEBHOOKS/CALLBACKS where we must look up the config by Tenant ID.
     *
     * @param int $tenantId The ID of the tenant whose config is needed. <-- NEW PARAMETER
     * @param string $gatewayKey The key of the desired gateway (e.g., 'phonepe').
     * @return PaymentGatewayInterface
     * @throws Exception
     */
    protected function resolveVerificationGateway(int $tenantId, string $gatewayKey): PaymentGatewayInterface 
    {
        $config = TenantPaymentConfig::where('tenant_id', $tenantId)
                                    ->where('gateway_type', $gatewayKey) // Match the specific gateway
                                    ->first();

        if (!$config) {
            \Log::error("PaymentService failed: Tenant ID {$tenantId} does not have a config for {$gatewayKey}.");
            throw new Exception("Payment verification failed. Missing tenant configuration for {$gatewayKey}.");
        }

        $credentials = $config->credentials;
        $gatewayClass = $this->gateways[$gatewayKey];

        return app($gatewayClass, ['credentials' => $credentials]);
    }
}