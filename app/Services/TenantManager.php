<?php

namespace App\Services;

class TenantManager
{
    /**
     * Create a new class instance.
     */
    public function __construct()
    {
        
    }
    
    protected ?int $currentTenantId = null;

    public function setCurrentTenantId(int $tenantId): void
    {
        $this->currentTenantId = $tenantId;
    }

    public function getCurrentTenantId(): ?int
    {
        return $this->currentTenantId;
    }

    public function forgetCurrentTenant(): void
    {
        $this->currentTenantId = null;
    }
}
