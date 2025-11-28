<?php

namespace App\Services\Payment;

use App\Models\BookingIntent;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\Facades\Log;
use Exception;

class PhonePeGateway implements PaymentGatewayInterface
{
    // These would typically be retrieved from secure Tenant settings in production
    protected string $merchantId;
    protected string $saltKey;
    protected int $saltIndex;
    protected string $baseUrl;

    public function __construct(array $credentials)
    {
        // For MVP, hardcode/use .env. In a scalable system, these would be injected
        // based on the tenant's payment configuration.
        $this->merchantId = $credentials['merchant_id'] ?? throw new Exception("PhonePe Merchant ID is missing.");
        $this->saltKey = $credentials['salt_key'] ?? throw new Exception("PhonePe Salt Key is missing.");
        $this->saltIndex = $credentials['salt_index'] ?? 1;
        
        // Use the correct PhonePe base URL for production/testing
         $this->baseUrl = env('APP_ENV') === 'local' 
            ? 'https://api-preprod.phonepe.com/apis/pg-sandbox'
            : 'https://api-preprod.phonepe.com/apis/pg-sandbox';
    }
    
    /**
     * Generates the SHA256 checksum for the PhonePe API request.
     *
     * @param string $payload The base64-encoded request body.
     * @param string $apiPath The specific PhonePe API path (e.g., '/pg/v1/pay').
     * @return string The X-VERIFY header value.
     */
    protected function generateXVerify(string $payload, string $apiPath): string
    {
        $string = $payload . $apiPath . $this->saltKey;
        $sha256 = hash('sha256', $string);
        return $sha256 . '###' . $this->saltIndex;
    }

    /**
     * @inheritDoc
     */
    public function initiatePayment(BookingIntent $intent): string
    {
        // Total amount is in decimal, PhonePe requires amount in paise (multiply by 100)
        $amountInPaise = round($intent->total_amount * 100);

        // Define the secure callback URL on your Laravel backend
        $redirectUrl = URL::route('public.payment.verify.phonepe', [
            'session_id' => $intent->session_id,
            'status' => 'callback', // A flag for your route to know it's a browser redirect
            'merchantTransactionId' => $intent->session_id
        ]);

        $requestPayload = [
            'merchantId' => $this->merchantId,
            'merchantTransactionId' => $intent->session_id, // Use session_id as the unique transaction ID
            'amount' => $amountInPaise,
            'redirectUrl' => $redirectUrl,
            'redirectMode' => 'REDIRECT',
            'callbackUrl' => URL::route('public.payment.webhook.phonepe'), // S2S Webhook URL
            'mobileNumber' => $intent->intent_data['visitor_info']['phone'] ?? '9999999999', // Phone is optional, use default if missing
            'paymentInstrument' => [
                'type' => 'PAY_PAGE',
            ],
        ];

        $base64Payload = base64_encode(json_encode($requestPayload));
        $apiPath = '/pg/v1/pay';
        $xVerify = $this->generateXVerify($base64Payload, $apiPath);

        $response = Http::withHeaders([
            'Content-Type' => 'application/json',
            'X-VERIFY' => $xVerify,
            'X-MERCHANT-ID' => $this->merchantId,
        ])->post("{$this->baseUrl}{$apiPath}", [
            'request' => $base64Payload
        ]);

        if ($response->successful()) {
            $data = $response->json('data');
            return $data['instrumentResponse']['redirectInfo']['url'] ?? throw new \Exception('Invalid redirect URL from PhonePe.');
        }

        Log::info('PhonePe Config Debug', [
    'merchantId' => $this->merchantId,
    'saltKey' => $this->saltKey,
    'saltIndex' => $this->saltIndex,
    'baseUrl' => $this->baseUrl,
]);
        Log::error('PhonePe Payment Initiation Failed', [
            'intent_id' => $intent->id,
            'response' => $response->body()
        ]);

        throw new \Exception('Payment gateway error. Please try again.');
    }

    /**
     * @inheritDoc
     */
    public function verifyPayment(string $transactionId): bool
    {
        // This method is for checking the final status, typically from a webhook/callback
        $apiPath = "/pg/v1/status/{$this->merchantId}/{$transactionId}";
        $xVerify = $this->generateXVerify('', $apiPath);

        $response = Http::withHeaders([
            'Content-Type' => 'application/json',
            'X-VERIFY' => $xVerify,
            'X-MERCHANT-ID' => $this->merchantId,
        ])->get("{$this->baseUrl}{$apiPath}");
        
        // This is a simplified check. Real implementation needs detailed status codes.
        if ($response->successful()) {
            $status = $response->json('data.state');
            return $status === 'COMPLETED' || $status === 'SUCCESS';
        }

        return false;
    }
}