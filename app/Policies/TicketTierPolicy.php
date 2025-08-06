<?php

namespace App\Policies;

use App\Models\User;
use App\Models\TicketTier;
use App\Models\BookableService;
use Illuminate\Auth\Access\Response;

class TicketTierPolicy
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
     *
     * @param \App\Models\User $user
     * @param \App\Models\TicketTier $ticketTier
     * @param \App\Models\BookableService $service
     * @return bool
     */
    public function view(User $user, TicketTier $ticketTier, BookableService $service): bool
    {
        // We perform the same robust check as our 'update' policy:
        // 1. Does the service belong to the user's tenant?
        // 2. Does the ticket tier actually belong to that service?
        return $user->tenant_id === $service->tenant_id &&
               $ticketTier->bookable_service_id === $service->id;
    }

    /**
     * Determine whether the user can create models.
     */
    public function create(User $user): bool
    {
        return false;
    }

    /**
     * Determine whether the user can update the model.
     */
    public function update(User $user, TicketTier $ticketTier, BookableService $service): bool
    {
         // Check 1: Does the tier belong to the service specified in the URL?
        $isChild = $ticketTier->bookable_service_id === $service->id;

        // Check 2: Does the service itself belong to the authenticated user's tenant?
        $tenantOwnsService = $user->tenant_id === $service->tenant_id;
        // The user is only authorized if BOTH checks are true.
        return $isChild && $tenantOwnsService;
    }

    /**
     * Determine whether the user can delete the model.
     */
    public function delete(User $user, TicketTier $tier, BookableService $service): bool
    {
        // Use the same robust, two-part check.
        return $tier->bookable_service_id === $service->id && $user->tenant_id === $service->tenant_id;
    }

    /**
     * Determine whether the user can restore the model.
     */
    public function restore(User $user, TicketTier $ticketTier): bool
    {
        return false;
    }

    /**
     * Determine whether the user can permanently delete the model.
     */
    public function forceDelete(User $user, TicketTier $ticketTier): bool
    {
        return false;
    }
}
