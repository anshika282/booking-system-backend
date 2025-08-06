<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\BookableService;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Log;
use App\Services\BookableServiceManager;
use Illuminate\Database\Eloquent\SoftDeletes;
use App\Http\Resources\BookableServicesResource;
use App\Http\Requests\StoreBookableServiceRequest;
use App\Http\Requests\UpdateBookableServiceConfigRequest;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;

class BookableServiceController extends Controller
{
    use AuthorizesRequests,SoftDeletes; 

    protected BookableServiceManager $bookableServiceManager;

    public function __construct(BookableServiceManager $bookableServiceManager)
    {
        $this->bookableServiceManager = $bookableServiceManager;
    }

    /**
     * Store a newly created bookable service in storage.
     *
     * @param StoreBookableServiceRequest $request
     * @return JsonResponse
     */
    public function store(StoreBookableServiceRequest $request): JsonResponse
    {
        try {
            $service = $this->bookableServiceManager->createService($request->validated());

            // Load the relationship to ensure it's included in the response resource.
            $service->load('serviceable');

            return response()->json(
                new BookableServicesResource($service),
                201 // HTTP 201 Created
            );

        } catch (\InvalidArgumentException $e) {
            return response()->json(['error' => $e->getMessage()], 422); // Unprocessable Entity
        } catch (\Exception $e) {
            Log::error('Service creation failed: ' . $e->getMessage());
            return response()->json(['error' => 'An unexpected error occurred while creating the service.'], 500);
        }
    }

    /**
     * Display a listing of the resource.
     *
     * @param Request $request
     * @return JsonResponse
     */
    public function index(Request $request): JsonResponse
    {
        try {
            // Validate incoming query parameters for safety and correctness.
            $validated = $request->validate([
                'status' => 'sometimes|string|in:active,draft,archived',
                'search' => 'sometimes|string|max:100',
                'sort_by' => 'sometimes|string|in:name,created_at,status',
                'sort_dir' => 'sometimes|string|in:asc,desc',
                'per_page' => 'sometimes|integer|min:1|max:100',
            ]);

            // Organize validated data for the service layer.
            $filters = [
                'status' => $validated['status'] ?? null,
                'search' => $validated['search'] ?? null,
            ];

            $sorting = [
                'sort_by' => $validated['sort_by'] ?? 'created_at',
                'sort_dir' => $validated['sort_dir'] ?? 'desc',
            ];

            $perPage = $validated['per_page'] ?? 15;

            $services = $this->bookableServiceManager->getServices($filters, $sorting, $perPage);
            
            // Use the same API Resource, which now automatically handles paginated collections.
            return BookableServicesResource::collection($services)->response();

        } catch (\Illuminate\Validation\ValidationException $e) {
            return response()->json(['errors' => $e->errors()], 422);
        } catch (\Exception $e) {
            Log::error('Failed to retrieve services: ' . $e->getMessage());
            return response()->json(['error' => 'An unexpected error occurred while fetching services.'], 500);
        }
    }

     /**
     * Display the specified resource.
     *
     * @param BookableService $service The service instance from route-model binding.
     * @return JsonResponse
     */
    public function show(BookableService $service): JsonResponse
    {
        // Step 1: Authorize the request.
        // The policy will ensure the user's tenant_id matches the service's tenant_id.
        // If not, it will automatically throw a 403 Forbidden exception.
        $this->authorize('view', $service);

        // Step 2: Eager load the necessary relationship for the response.
        // This is crucial for performance and avoids the N+1 problem.
        $service->load('serviceable');
        
        // Step 3: Return the data formatted by our consistent API resource.
        return (new BookableServicesResource($service))->response();
    }

      /**
     * [PATCH /services/{service}]
     * Partially update the specified service's configuration.
     *
     * @param UpdateBookableServiceConfigRequest $request
     * @param BookableService $service
     * @return JsonResponse
     */
    public function update(UpdateBookableServiceConfigRequest $request, BookableService $service): JsonResponse
    {
        // 1. Authorize the action
        $this->authorize('update', $service);
        
        // 2. Call the dedicated service method with the validated (and filtered) data
        $updatedService = $this->bookableServiceManager->updateConfig(
            $service,
            $request->validated()
        );

        // 3. Return the updated resource
        return (new BookableServicesResource($updatedService))->response();
    }
    

     /**
     * Remove the specified resource from storage.
     *
     * @param BookableService $service
     * @return JsonResponse
     */
    public function destroy(BookableService $service): JsonResponse
    {
        // Step 1: Authorize the action using our policy.
        $this->authorize('delete', $service);

        try {
            $this->bookableServiceManager->deleteService($service);
            
            // Step 2: Return a 204 No Content response on successful deletion.
            // This is the RESTful standard.
            return response()->json(null, 204);

        } catch (\Exception $e) {
            Log::error(
                "Failed to delete service {$service->id}", 
                ['error' => $e->getMessage()]
            );
            // If the service has future bookings, this will be caught here.
            return response()->json(['error' => $e->getMessage()], 422); // Unprocessable Entity
        }
    }

    /**
     * Restore the specified soft-deleted resource.
     * The {service} parameter will be resolved using withTrashed() implicitly by Laravel.
     *
     * @param int $serviceId
     * @return JsonResponse
     */
    public function restore(int $serviceId): JsonResponse
    {
        // Manually find the service including soft-deleted ones.
        $service = BookableService::withTrashed()->findOrFail($serviceId);

        // Authorize the action using our policy.
        $this->authorize('restore', $service);

        $service->restore();

        // Eager-load for the response
        $service->load('serviceable');

        // Return the restored service.
        return (new BookableServicesResource($service))->response();
    }
}
