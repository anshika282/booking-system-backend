<?php

namespace App\Policies;

use App\Models\BookableService;
use App\Models\User;
use Illuminate\Auth\Access\Response;

class BookableServicePolicy
{
    /**
     * Determine whether the user can view any models.
     */
    public function viewAny(User $user): bool
    {
        return false;
    }

    /**
     * Determine whether the user can view the model.
     */
    public function view(User $user, BookableService $bookableService): bool
    {
        // A user can only view a service if it belongs to their tenant.
        return $user->tenant_id === $bookableService->tenant_id;
    }

    /**
     * Determine whether the user can create models.
     */
    public function create(User $user): bool
    {
        \Log::info('tenant id is in policy : ' . !is_null($user->tenant_id));
        return !is_null($user->tenant_id);
    }

    /**
     * Determine whether the user can update the model.
     */
    public function update(User $user, BookableService $bookableService): bool
    {
        // A user can only update a service if it belongs to their tenant.
        \Log::info('tenant id is in policy : ' . $user->tenant_id);
        return $user->tenant_id === $bookableService->tenant_id;

    }

    /**
     * Determine whether the user can delete the model.
     */
    public function delete(User $user, BookableService $bookableService): bool
    {
        // The logic is the same: only the owner tenant can delete.
        return $user->tenant_id === $bookableService->tenant_id;
    }

    /**
     * Determine whether the user can restore the model.
     */
    public function restore(User $user, BookableService $bookableService): bool
    {
        // The logic is the same: only the owner tenant can delete.
        return $user->tenant_id === $bookableService->tenant_id;
    }

    /**
     * Determine whether the user can permanently delete the model.
     */
    public function forceDelete(User $user, BookableService $bookableService): bool
    {
        return false;
    }
}
