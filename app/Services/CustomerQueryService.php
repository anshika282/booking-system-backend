<?php

namespace App\Services;

use App\Models\Customers;
use App\Services\TenantManager;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

class CustomerQueryService
{
    protected TenantManager $tenantManager;

    public function __construct(TenantManager $tenantManager)
    {
        $this->tenantManager = $tenantManager;
    }

    /**
     * Retrieves a paginated list of customers who have booked with the current tenant.
     */
    public function getCustomersForTenant(array $filters = [], int $perPage = 15): LengthAwarePaginator
    {
        $tenantId = $this->tenantManager->getCurrentTenantId();

        $query = Customers::query()
            // This is the key: only show customers who have at least one booking
            // associated with the current tenant.
            ->whereHas('bookings', fn($q) => $q->where('tenant_id', $tenantId));

        // Apply search filter for name or email
        if (!empty($filters['search'])) {
            $searchTerm = $filters['search'];
            $query->where(function ($q) use ($searchTerm) {
                $q->where('name', 'LIKE', "%{$searchTerm}%")
                  ->orWhere('email', 'LIKE', "%{$searchTerm}%");
            });
        }
        
        $query->orderBy('name', 'asc');

        return $query->paginate($perPage);
    }

    /**
     * Retrieves a single customer's details along with their booking summary for the current tenant.
     */
    public function getCustomerDetailsForTenant(Customer $customer): array
    {
        $tenantId = $this->tenantManager->getCurrentTenantId();

        // Eager-load the bookings, but ONLY for the current tenant.
        $customer->load(['bookings' => fn($q) => $q->where('tenant_id', $tenantId)->orderBy('created_at', 'desc')]);

        // Calculate the summary stats efficiently from the loaded relationship.
        $tenantSpecificBookings = $customer->bookings;
        
        $summary = [
            'total_bookings' => $tenantSpecificBookings->count(),
            'total_spent' => $tenantSpecificBookings->sum('total_amount'),
        ];
        
        return [
            'customer' => $customer,
            'summary' => $summary,
        ];
    }
}