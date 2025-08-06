<?php

namespace App\Services;

use App\Models\TicketTier;
use App\Models\BookableService;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use App\Exceptions\InvalidTicketTierUpdateException;

class TicketTierManager
{
    /**
     * Creates a new ticket tier for a given service.
     *
     * @param BookableService $service The service to add the tier to.
     * @param array $data The validated data for the new tier.
     * @return TicketTier
     */
    public function createTier(BookableService $service, array $data): TicketTier
    {
        // Add the service and tenant IDs to the data array.
        $data['bookable_service_id'] = $service->id;
        $data['tenant_id'] = $service->tenant_id;

        // Business Rule: For appointments, often only one "tier" (price point) is allowed.
        // This can be enforced here if needed, but for MVP, we'll allow multiple for flexibility.
        /*
        if ($service->serviceable_type === 'App\\Models\\ServiceAppointment' && $service->ticketTiers()->count() > 0) {
            throw new \Exception('Appointments can only have one pricing tier.');
        }
        */

        return TicketTier::create($data);
    }

    /**
     * Updates an existing ticket tier.
     *
     * @param TicketTier $tier The tier to update.
     * @param array $data The validated data.
     * @return TicketTier
     */
    public function updateTier(TicketTier $tier, array $data): TicketTier
    {
        $minQuantity = $data['min_quantity'] ?? $tier->min_quantity;
        $maxQuantity = $data['max_quantity'] ?? $tier->max_quantity;

        if ($maxQuantity < $minQuantity) {
            throw new InvalidTicketTierUpdateException('Max quantity cannot be less than Min quantity.');
        }
        $tier->update($data);
        // return $tier;
        return $tier->fresh();
    }

    /**
     * Deletes a ticket tier.
     *
     * @param TicketTier $tier
     * @return bool
     */
    public function deleteTier(TicketTier $tier): bool
    {
        // Business Rule: Prevent deletion if this tier has been used in past bookings.
        // A more advanced check would query the `bookings` table's JSON snapshot.
        // For now, a direct delete is acceptable for the MVP.
        return $tier->delete();
    }

    /**
     * Reconciles the state of all ticket tiers for a given service.
     * This will create, update, and delete tiers to match the provided data.
     * This operation is atomic, wrapped in a database transaction.
     *
     * @param BookableService $service The service being configured.
     * @param array $tiersData The complete array of desired ticket tiers.
     * @return Collection The new, reconciled collection of ticket tiers.
     */
    public function reconcileTiers(BookableService $service, array $tiersData): Collection
    {
        return DB::transaction(function () use ($service, $tiersData) {
            
            $incomingTierIds = [];
            $reconciledTiers = new Collection();

            // Step 1 & 2: Iterate through the incoming data to update existing tiers and create new ones.
            foreach ($tiersData as $index => $tierData) {
                $tierData['order_column'] = $index; // Automatically set order based on array position.

                // If an ID is present, it's an existing tier to be updated.
                if (isset($tierData['id'])) {
                    $tierId = $tierData['id'];
                    $tier = TicketTier::where('bookable_service_id', $service->id)
                                      ->where('id', $tierId)
                                      ->first();

                    if (!$tier) {
                        // Throw an exception if the client tries to update a tier that doesn't belong to this service.
                        throw new ModelNotFoundException("TicketTier with ID {$tierId} not found for this service.");
                    }
                    
                    $reconciledTiers->push($this->updateTier($tier, $tierData));
                    $incomingTierIds[] = $tier->id;

                } else {
                    // If no ID is present, it's a new tier to be created.
                    $newTier = $this->createTier($service, $tierData);
                    $reconciledTiers->push($newTier);
                    $incomingTierIds[] = $newTier->id;
                }
            }

            // Step 3: Delete any tiers that exist in the database for this service
            // but were not included in the incoming payload.
            $service->ticketTiers()->whereNotIn('id', $incomingTierIds)->delete();
            
            return $reconciledTiers;
        });
    }

    /**
     * Updates the order_column for a collection of ticket tiers.
     * This is an atomic operation.
     *
     * @param array $orderedTierIds An array of TicketTier IDs in their new desired order.
     * @param int $tenantId The ID of the tenant performing the action, for security.
     * @return bool
     */
    public function updateOrder(array $orderedTierIds, int $tenantId,int $serviceId): bool
    {
        if (empty($orderedTierIds)) {
            return true; // Nothing to do.
        }

        return DB::transaction(function () use ($orderedTierIds, $tenantId, $serviceId) {
            // First, ensure all provided IDs belong to the current tenant to prevent cross-tenant updates.
            // This is a critical security check.
            $count = TicketTier::whereIn('id', $orderedTierIds)
                                ->where('tenant_id', $tenantId)
                                ->where('bookable_service_id', $serviceId)
                                ->count();
            if ($count !== count($orderedTierIds)) {
                // Throw an exception if the client tries to reorder tiers they don't own.
                throw new \Illuminate\Validation\ValidationException(
                    null,
                    response()->json([
                        'message' => 'The given data was invalid.',
                        'errors' => [
                            'ordered_tier_ids' => ['One or more ticket tier IDs are invalid or do not belong to the specified service.'],
                        ],
                    ], 422)
                );
            }

            // Loop through the ordered IDs and issue an UPDATE for each one.
            foreach ($orderedTierIds as $index => $tierId) {
                TicketTier::where('id', $tierId)->update(['order_column' => $index]);
            }

            return true;
        });
    }
}