<?php

namespace App\Policies;

use App\Models\Customers;
use App\Models\User;
use App\Services\TenantManager;

class CustomerPolicy
{
    /**
     * Determine whether the user can view the model.
     * A user can only view a customer if that customer has at least one booking
     * with the user's tenant.
     */
    public function view(User $user, Customers $customer): bool
    {
        return $customer->bookings()->where('tenant_id', $user->tenant_id)->exists();
    }
}