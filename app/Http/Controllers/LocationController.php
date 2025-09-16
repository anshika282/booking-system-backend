<?php

namespace App\Http\Controllers;

use App\Models\Location;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use App\Http\Resources\LocationResource;
use App\Http\Requests\StoreLocationRequest;
use App\Http\Requests\UpdateLocationRequest;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;

class LocationController extends Controller
{
    use AuthorizesRequests;
  public function index(): JsonResponse
    {
        $this->authorize('viewAny', Location::class);
        $locations = request()->user()->tenant->locations()->paginate(100); // Paginate with a high number
        return LocationResource::collection($locations)->response();
    }

    public function store(StoreLocationRequest $request): JsonResponse
    {
        $this->authorize('create', Location::class);
        $location = request()->user()->tenant->locations()->create($request->validated());
        return (new LocationResource($location))->response()->setStatusCode(201);
    }

    public function show(Location $location): JsonResponse
    {
        $this->authorize('view', $location);
        return (new LocationResource($location))->response();
    }

    public function update(UpdateLocationRequest $request, Location $location): JsonResponse
    {
        $this->authorize('update', $location);
        $location->update($request->validated());
        return (new LocationResource($location->fresh()))->response();
    }

    public function destroy(Location $location): JsonResponse
    {
        $this->authorize('delete',[ $$location]);
        // Optional: Check if location is in use before deleting
        if ($location->serviceTicketedEvents()->exists()) {
            return response()->json(['error' => 'Cannot delete a location that is currently in use by a service.'], 422);
        }
        $location->delete();
        return response()->json(null, 204);
    }
}
