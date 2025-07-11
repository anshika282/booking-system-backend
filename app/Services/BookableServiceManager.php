<?php

namespace App\Services;

use App\Models\BookableService;
use App\Models\ServiceAppointment;
use App\Models\ServiceTicketedEvent;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

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
            $serviceable = match ($data['service_type']) {
                'ticketed_event' => ServiceTicketedEvent::create([
                    'tenant_id' => $tenantId,
                    'venue_name' => $data['venue_name'],
                    'requires_waiver' => $data['requires_waiver'],
                    // 'address' and 'seating_map_config' can be added later or made optional here
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
                'capacity_consumption_mode' => $data['capacity_consumption_mode'],
                'slot_selection_mode' => $data['slot_selection_mode'],
                'booking_window_min_days' => $data['booking_window_min_days'],
                'booking_window_max_days' => $data['booking_window_max_days'],
                'status' => 'draft', // Services are created as drafts by default.
            ]);
            
            // Associate the polymorphic relationship and save.
            $bookableService->serviceable()->associate($serviceable);
            $bookableService->save();

            return $bookableService;
        });
    }
}
