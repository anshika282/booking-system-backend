<?php

namespace App\Policies;

use App\Models\AddOn;
use App\Models\User;

class AddOnPolicy
{
    /**
     * Determine whether the user can view the model.
     */
    public function view(User $user, AddOn $addOn): bool
    {
        // A user can view an add-on if it belongs to their tenant.
        return $user->tenant_id === $addOn->tenant_id;
    }
    
    /**
     * Determine whether the user can update the model.
     */
    public function update(User $user, AddOn $addOn): bool
    {
        return $user->tenant_id === $addOn->tenant_id;
    }

    /**
     * Determine whether the user can delete the model.
     */
    public function delete(User $user, AddOn $addOn): bool
    {
        return $user->tenant_id === $addOn->tenant_id;
    }
}