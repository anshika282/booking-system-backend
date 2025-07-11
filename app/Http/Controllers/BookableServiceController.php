<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreBookableServiceRequest;
use App\Http\Resources\BookableServiceResource;
use App\Services\BookableServiceManager;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Log;
use Illuminate\Http\Request;

class BookableServiceController extends Controller
{
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
                new BookableServiceResource($service),
                201 // HTTP 201 Created
            );

        } catch (\InvalidArgumentException $e) {
            return response()->json(['error' => $e->getMessage()], 422); // Unprocessable Entity
        } catch (\Exception $e) {
            Log::error('Service creation failed: ' . $e->getMessage());
            return response()->json(['error' => 'An unexpected error occurred while creating the service.'], 500);
        }
    }
}
