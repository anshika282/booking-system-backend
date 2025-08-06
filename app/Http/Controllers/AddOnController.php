<?php

namespace App\Http\Controllers;

use App\Models\AddOn;
use Illuminate\Http\Request;
use App\Services\AddOnManager;
use App\Models\BookableService;
use Illuminate\Http\JsonResponse;
use App\Http\Resources\AddOnResource;
use App\Http\Requests\StoreAddOnRequest;
use App\Http\Requests\UpdateAddOnRequest;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;

class AddOnController extends Controller
{
    use AuthorizesRequests;

    protected AddOnManager $addOnManager;

    public function __construct(AddOnManager $addOnManager)
    {
        $this->addOnManager = $addOnManager;
    }

   /**
     * List all paginated add-ons for a specific service.
     */
    public function index(Request $request, BookableService $service): JsonResponse
    {
        $this->authorize('view', $service);

        // Validate the optional 'per_page' query parameter.
        $validated = $request->validate([
            'per_page' => 'sometimes|integer|min:1|max:100',
        ]);

        $perPage = $validated['per_page'] ?? 15; // Default to 15 items per page

        // Call the service method to get the paginated results.
        $addOns = $this->addOnManager->getAddOnsForService($service, $perPage);
        
        // The AddOnResource::collection() helper automatically detects a paginated
        // result and formats the JSON response correctly with 'data', 'links', and 'meta' keys.
        return AddOnResource::collection($addOns)->response();
    }

    /**
     * Store a new add-on for a service.
     */
    public function store(StoreAddOnRequest $request, BookableService $service): JsonResponse
    {
        $this->authorize('update', $service); // Permission to update service allows adding add-ons
        $addOn = $this->addOnManager->createAddOn($service, $request->validated());
        return (new AddOnResource($addOn))->response()->setStatusCode(201);
    }

    /**
     * [GET] /add-ons/{addOn}
     * Display the specified add-on.
     *
     * @param AddOn $addOn The AddOn instance from route-model binding.
     * @return JsonResponse
     */
    public function show(BookableService $service, AddOn $addOn): JsonResponse
    {
        // Authorize that the user has permission to view this specific add-on.
        // The policy will check for tenant ownership.
        $this->authorize('view', $addOn);
        
        // Return the single resource, formatted by our consistent AddOnResource.
        return (new AddOnResource($addOn))->response();
    }

    /**
     * Update an existing add-on.
     */
    public function update(UpdateAddOnRequest $request, BookableService $service,  AddOn $addOn): JsonResponse
    {
        $this->authorize('update', $addOn);
        $addOn = $this->addOnManager->updateAddOn($addOn, $request->validated());
        return (new AddOnResource($addOn))->response();
    }

    /**
     * Delete an add-on.
     */
    public function destroy(BookableService $service, AddOn $addOn): JsonResponse
    {
        $this->authorize('delete', $addOn);
        $this->addOnManager->deleteAddOn($addOn);
        return response()->json(null, 204);
    }
}