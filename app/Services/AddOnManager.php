<?php

namespace App\Services;

use App\Models\AddOn;
use App\Models\BookableService;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

class AddOnManager
{
     /**
     * Retrieves a paginated list of add-ons for a specific service.
     *
     * @param BookableService $service
     * @param int $perPage
     * @return LengthAwarePaginator
     */
    public function getAddOnsForService(BookableService $service, int $perPage = 15): LengthAwarePaginator
    {
        // Use the relationship to start the query, ensuring we only get add-ons for this service.
        // Then, order them and use the paginate() method.
        return $service->addons()
                      ->orderBy('name', 'asc')
                      ->paginate($perPage);
    }
    
    /**
     * Creates a new add-on for a given service.
     */
    public function createAddOn(BookableService $service, array $data): AddOn
    {
        $data['bookable_service_id'] = $service->id;
        $data['tenant_id'] = $service->tenant_id;

        return AddOn::create($data);
    }

    /**
     * Updates an existing add-on.
     */
    public function updateAddOn(AddOn $addOn, array $data): AddOn
    {
        $addOn->update($data);
        return $addOn->fresh();
    }

    /**
     * Deletes an add-on.
     */
    public function deleteAddOn(AddOn $addOn): bool
    {
        // Add business logic here if needed (e.g., prevent deletion if it's part of a past booking).
        return $addOn->delete();
    }
}