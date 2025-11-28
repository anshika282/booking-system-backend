<?php

namespace App\Services;

use App\Models\TenantPaymentConfig;
use Illuminate\Support\Facades\DB;

class PaymentConfigManager
{
    protected TenantManager $tenantManager;

    public function __construct(TenantManager $tenantManager)
    {
        $this->tenantManager = $tenantManager;
    }

    /**
     * Retrieves the default payment configuration for the current tenant.
     */
    public function getConfig(): ?TenantPaymentConfig
    {
        $tenantId = $this->tenantManager->getCurrentTenantId();
        
        // Find the configuration marked as the default
        return TenantPaymentConfig::where('tenant_id', $tenantId)
                                 ->where('is_default', true)
                                 ->first();
    }

    /**
     * Creates or updates the payment configuration for the tenant.
     * This uses firstOrNew and save, ensuring only one config per type.
     *
     * @param array $data The validated data (gateway_type, credentials, etc.)
     * @return TenantPaymentConfig
     */
    public function saveConfig(array $data): TenantPaymentConfig
    {
        $tenantId = $this->tenantManager->getCurrentTenantId();

        // Use DB Transaction for atomicity, especially for setting/unsetting 'is_default'
        return DB::transaction(function () use ($tenantId, $data) {

            // 1. Unset the 'is_default' flag on the OLD default config
            TenantPaymentConfig::where('tenant_id', $tenantId)
                               ->where('is_default', true)
                               ->update(['is_default' => false]);
            
            // 2. Find the existing config for this gateway type or create a new one
            $config = TenantPaymentConfig::firstOrNew([
                'tenant_id' => $tenantId,
                'gateway_type' => $data['gateway_type'],
            ]);
            
            // 3. Fill and save the new data
            $config->fill([
                'credentials' => $data['credentials'],
                'is_default' => true, // Make the current one the new default
            ]);
            
            $config->save();

            return $config;
        });
    }
}