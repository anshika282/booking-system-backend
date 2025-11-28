<?php

namespace App\Http\Controllers;

use App\Models\TenantPaymentConfig;
use App\Services\PaymentConfigManager;
use Illuminate\Http\JsonResponse;
use App\Http\Requests\StorePaymentConfigRequest;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;

class PaymentConfigController extends Controller
{
    use AuthorizesRequests;

    protected PaymentConfigManager $configManager;

    public function __construct(PaymentConfigManager $configManager)
    {
        $this->configManager = $configManager;
    }

    /**
     * [GET] /payment/config
     * Fetches the current default payment configuration.
     * Accessible only by the 'owner' via the route middleware.
     */
    public function show(): JsonResponse
    {
        try {
            $config = $this->configManager->getConfig();

           if (!$config) {
                // If no config is found (new tenant), return 200 OK with null data.
                // This tells the frontend the request succeeded, but the resource is not yet configured.
                return response()->json(['data' => null], 200);
            }
            
            // Use a resource here if you want to filter what is returned, 
            // but returning the model is simpler for an internal configuration.
            return response()->json(['data' => $config]);

        } catch (\Exception $e) {
            \Log::error('Failed to fetch payment config: ' . $e->getMessage());
            return response()->json(['error' => 'An internal server error occurred.'], 500);
        }
    }

    /**
     * [PUT] /payment/config
     * Saves or updates the tenant's default payment configuration.
     * Accessible only by the 'owner' via the route middleware.
     */
    public function update(StorePaymentConfigRequest $request): JsonResponse
    {
        try {
            $config = $this->configManager->saveConfig($request->validated());

            return response()->json([
                'message' => 'Payment configuration saved successfully.',
                'data' => $config
            ], 200);

        } catch (\Exception $e) {
            \Log::error('Failed to save payment config: ' . $e->getMessage());
            return response()->json(['error' => 'An internal server error occurred.'], 500);
        }
    }
}