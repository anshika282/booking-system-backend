<?php

namespace App\Policies;

use App\Models\User;
use App\Models\Location;


class LocationPolicy
{
     /**
     * A user can view any location that belongs to their own tenant.
     */
    public function viewAny(User $user): bool
    {
        return !is_null($user->tenant_id);
    }

    /**
     * A user can view a specific location if it belongs to their tenant.
     */
    public function view(User $user, Location $location): bool
    {
        return $user->tenant_id === $location->tenant_id;
    }

    /**
     * A user can create a location for their own tenant.
     */
    public function create(User $user): bool
    {
        return !is_null($user->tenant_id);
    }

    /**
     * A user can update a location if it belongs to their tenant.
     */
    public function update(User $user, Location $location): bool
    {
        return $user->tenant_id === $location->tenant_id;
    }
    /**
     * A user can delete a location if it belongs to their tenant.
     */
    public function delete(User $user, Location $location): bool
    {
        return $user->tenant_id === $location->tenant_id;
    }
}
