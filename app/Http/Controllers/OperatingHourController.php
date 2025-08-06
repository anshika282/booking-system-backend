<?php

namespace App\Http\Controllers;

use App\Models\BookableService;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Log;
use App\Services\OperatingHourManager;
use App\Http\Resources\OperatingHourResource;
use App\Http\Requests\UpdateOperatingHoursRequest;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;

class OperatingHourController extends Controller
{
    use AuthorizesRequests; 
    protected OperatingHourManager $operatingHourManager;

    public function __construct(OperatingHourManager $operatingHourManager)
    {
        $this->operatingHourManager = $operatingHourManager;
    }

     /**
     * Display a listing of the operating hours for a specific service.
     *
     * @param BookableService $service The service instance from route-model binding.
     * @return JsonResponse
     */
    public function index(BookableService $service): JsonResponse
    {
        // Authorize that the user can view the service (and thus its schedule).
        $this->authorize('view', $service);

        try {
            // The relationship 'operatingHours' on the BookableService model fetches all related hours.
            // We use the same resource to ensure a consistent response format.
            $hours = $service->operatingHours()->orderBy('day_of_week')->get();
            
            return OperatingHourResource::collection($hours)->response();

        } catch (\Exception $e) {
            Log::error(
                "Failed to fetch operating hours for service {$service->id}", 
                ['error' => $e->getMessage()]
            );
            return response()->json(['error' => 'An unexpected error occurred while fetching the schedule.'], 500);
        }
    }

     /**
     * Update the operating hours for a specific bookable service.
     *
     * @param UpdateOperatingHoursRequest $request The validated request.
     * @param BookableService $service The service instance from route-model binding.
     * @return JsonResponse
     */
    public function update(UpdateOperatingHoursRequest $request, BookableService $service): JsonResponse
    {
        $this->authorize('update', $service);
        
        try {
            $validated = $request->validated();
            $generationDays = $validated['generate_slots_for_days'] ?? null;

            $result = $this->operatingHourManager->replaceScheduleAndGenerateSlots(
                $service, 
                $validated['hours'],
                $generationDays
            );

            // Return a meaningful success message instead of the raw slot data,
            // which could be a very large payload.
            return response()->json([
                'message' => 'Schedule updated successfully.',
                'slots_generated' => $result['slots_generated'],
                'data' => OperatingHourResource::collection($result['hours']),
            ]);

        } catch (\Exception $e) {
            Log::error(
                "Failed to update operating hours for service {$service->id}", 
                ['error' => $e->getMessage()]
            );
            return response()->json(['error' => 'An unexpected error occurred while updating the schedule.'], 500);
        }
    }
}