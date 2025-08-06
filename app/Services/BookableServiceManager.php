<?php

namespace App\Services;

use InvalidArgumentException;
use App\Models\BookableService;
use App\Models\ServiceAppointment;
use Illuminate\Support\Facades\DB;
use App\Models\ServiceTicketedEvent;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

class BookableServiceManager
{
    /**
     * Create a new class instance.
     */
    protected TenantManager $tenantManager;

    public function __construct(TenantManager $tenantManager)
    {
        $this->tenantManager = $tenantManager;
    }

    /**
     * Creates a new bookable service with its specific polymorphic type.
     * This operation is wrapped in a database transaction.
     *
     * @param array $data The validated data from the request.
     * @return BookableService The newly created bookable service.
     * @throws InvalidArgumentException|\Exception
     */
    public function createService(array $data): BookableService
    {
        $tenantId = $this->tenantManager->getCurrentTenantId();
        if (!$tenantId) {
            // This should theoretically be caught by middleware, but serves as a failsafe.
            throw new \Exception("Tenant context is not set.");
        }

        // The entire creation process is atomic. If any part fails, everything is rolled back.
        return DB::transaction(function () use ($data, $tenantId) {
            
            // Step 1: Create the specific service type (the "serviceable" model).
            // A `match` expression is a modern and clean way to handle this.
            
            // Log the location_id for debugging purposes.
            \Log::info('Creating service with location_id: ');
                    \Log::info($data['location_id']);
            $serviceable = match ($data['service_type']) {
                'ticketed_event' => ServiceTicketedEvent::create([
                    'tenant_id' => $tenantId,
                    'venue_name' => $data['venue_name'],
                    'requires_waiver' => $data['requires_waiver'],
                    'location_id' => $data['location_id'],
                ]),
                'appointment' => ServiceAppointment::create([
                    'tenant_id' => $tenantId,
                    'duration_minutes' => $data['duration_minutes'],
                    'buffer_time_minutes' => $data['buffer_time_minutes'],
                    'requires_provider' => $data['requires_provider'],
                ]),
                default => throw new InvalidArgumentException('Invalid service type provided.'),
            };

            // Step 2: Create the main BookableService and link it polymorphically.
            $bookableService = new BookableService([
                'tenant_id' => $tenantId,
                'name' => $data['name'],
                'duration_minutes' => $data['duration_minutes'],
                'slot_consumption_mode' => $data['slot_consumption_mode'],
                'slot_selection_mode' => $data['slot_selection_mode'],
                'booking_window_min_days' => $data['booking_window_min_days'],
                'booking_window_max_days' => $data['booking_window_max_days'],
                'status' => 'draft', // Services are created as drafts by default.
                'default_capacity' => $data['default_capacity'],
            ]);
            
            // Associate the polymorphic relationship and save.
            $bookableService->serviceable()->associate($serviceable);
            $bookableService->save();

            return $bookableService;
        });
    }

    /**
     * Retrieves a paginated list of bookable services for the current tenant,
     * with support for filtering, searching, and sorting.
     *
     * @param array $filters Associative array of filters ('status', 'search').
     * @param array $sorting Associative array for sorting ('sort_by', 'sort_dir').
     * @param int $perPage Number of items per page.
     * @return LengthAwarePaginator
     */
    public function getServices(array $filters = [], array $sorting = [], int $perPage = 15): LengthAwarePaginator
    {
        $tenantId = $this->tenantManager->getCurrentTenantId();
        if (!$tenantId) {
            throw new \Exception("Tenant context is not set.");
        }

        // Start the query, scoped to the current tenant and eager-load the polymorphic relationship.
        // Eager loading is critical for performance to avoid N+1 query problems.
        $query = BookableService::query()
            ->where('tenant_id', $tenantId)
            ->with('serviceable');

        // Apply filters
        if (!empty($filters['status'])) {
            $query->where('status', $filters['status']);
        }

        if (!empty($filters['search'])) {
            $searchTerm = $filters['search'];
            $query->where('name', 'LIKE', "%{$searchTerm}%");
        }

        // Apply sorting
        // We use a whitelist to prevent users from sorting by arbitrary/unindexed columns.
        $sortBy = $sorting['sort_by'] ?? 'created_at';
        $sortDir = $sorting['sort_dir'] ?? 'desc';
        $allowedSorts = ['name', 'created_at', 'status'];

        if (in_array($sortBy, $allowedSorts)) {
            $query->orderBy($sortBy, $sortDir);
        }

        // Paginate the results
        return $query->paginate($perPage);
    }

    /**
     * Partially updates the configuration of a bookable service.
     *
     * @param BookableService $service The service to update.
     * @param array $data The validated data containing only the allowed config fields.
     * @return BookableService
     */
    public function updateConfig(BookableService $service, array $data): BookableService
    {
        return DB::transaction(function () use ($service, $data) {
            
            // Step 1: Update the main BookableService model with its relevant fields.
            // We can use array filtering to ensure we only try to update columns that exist on this model.
            $serviceFillable = (new BookableService())->getFillable();
            $serviceData = array_filter($data, fn($key) => in_array($key, $serviceFillable), ARRAY_FILTER_USE_KEY);
            
            if (!empty($serviceData)) {
                $service->update($serviceData);
            }

            // Step 2: Check the type of the polymorphic relationship and update it.
            $serviceable = $service->serviceable;

            if ($serviceable instanceof ServiceTicketedEvent) {
                // Filter the data for fields that belong to the ServiceTicketedEvent model.
                $serviceableFillable = (new ServiceTicketedEvent())->getFillable();
                $serviceableData = array_filter($data, fn($key) => in_array($key, $serviceableFillable), ARRAY_FILTER_USE_KEY);
                
                if (!empty($serviceableData)) {
                    $serviceable->update($serviceableData);
                }
            }
            // You could add a similar block for 'instanceof ServiceAppointment' if needed in the future.
            
            // Eager-load the relationship to ensure the response is complete.
            return $service->fresh()->load('serviceable');
        });
    }
    
    /**
     * Deletes a bookable service.
     * This will trigger a soft delete.
     *
     * @param BookableService $service
     * @return bool
     * @throws \Exception if there's a business rule violation.
     */
    public function deleteService(BookableService $service): bool
    {
        // BUSINESS RULE: As a best practice, prevent deletion if the service has active,
        // upcoming bookings. This check would be more complex in a real app,
        // likely checking the `bookings` table for confirmed bookings with a future start date.
        // For now, this demonstrates the principle.
        /*
        if ($service->bookings()->where('status', 'confirmed')->where('booking_date', '>=', now())->exists()) {
            throw new \Exception('Cannot delete a service that has active future bookings. Please cancel them first.');
        }
        */

        // The model event we registered on BookableService will handle deleting the serviceable child.
        // Calling delete() on a model with the SoftDeletes trait will perform a soft delete.
        return $service->delete();
    }

}
