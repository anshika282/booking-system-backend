<?php

namespace App\Http\Controllers;

use App\Models\TicketTier;
use Illuminate\Http\Request;
use App\Models\BookableService;
use App\Services\TenantManager;
use Illuminate\Validation\Rule;
use Illuminate\Http\JsonResponse;
use App\Services\TicketTierManager;
use Illuminate\Support\Facades\Log;
use App\Http\Resources\TicketTierResource;
use App\Http\Requests\StoreTicketTierRequest;
use App\Http\Requests\UpdateTicketTierRequest;
use App\Exceptions\InvalidTicketTierUpdateException;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;

class TicketTierController extends Controller
{
    use AuthorizesRequests;

    protected TicketTierManager $ticketTierManager;
    protected TenantManager $tenantManager;

    public function __construct(TicketTierManager $ticketTierManager, TenantManager $tenantManager)
    {
        $this->ticketTierManager = $ticketTierManager;
        $this->tenantManager = $tenantManager;
        
    }

    /**
     * List all ticket tiers for a specific service.
     */
    public function index(BookableService $service): JsonResponse
    {
        $this->authorize('view', $service);
        $tiers = $service->ticketTiers()
                ->orderBy('order_column')
                ->paginate(
                    perPage: request()->input('per_page', 15), // Default to 15 items per page
                    columns: ['*'], // Select all columns
                    pageName: 'page', // Query parameter for page number
                    page: request()->input('page', 1) // Current page
                );

            // Return paginated response with metadata
        return TicketTierResource::collection($tiers)->response()->setStatusCode(200);
    }

    /**
     * Store a new ticket tier for a service.
     */
    public function store(StoreTicketTierRequest $request, BookableService $service): JsonResponse
    {
        $this->authorize('update', $service); // Use 'update' permission on the parent service
        
        try {
            $tier = $this->ticketTierManager->createTier($service, $request->validated());
            return (new TicketTierResource($tier))->response()->setStatusCode(201);
        } catch (\Exception $e) {
            Log::error("Failed to create ticket tier for service {$service->id}", ['error' => $e->getMessage()]);
            return response()->json(['error' => 'An error occurred while creating the ticket tier.'], 500);
        }
    }

     /**
     * [GET] /services/{service}/ticket-tiers/{ticketTier}
     * Display the specified ticket tier.
     *
     * @param BookableService $service
     * @param TicketTier $ticketTier
     * @return JsonResponse
     */
    public function show(BookableService $service, TicketTier $ticketTier): JsonResponse
    {
        try{
            // The policy will check for tenant ownership and the parent-child relationship.
            $this->authorize('view', [$ticketTier, $service]);
                    
            // Return the single resource, formatted by our consistent TicketTierResource.
            return (new TicketTierResource($ticketTier))->response();
        } catch (ModelNotFoundException $e) {
            return response()->json(['message' => 'The requested resource could not be found.'], 422);
        }
    }

    /**
     * Update an existing ticket tier.
     */
    public function update(UpdateTicketTierRequest $request,  BookableService $service, TicketTier $ticketTier): JsonResponse
    {
        // dd($ticketTier);
        $this->authorize('update', [$ticketTier, $service]);
        
        try {
            $updatedTier = $this->ticketTierManager->updateTier($ticketTier, $request->validated());
            return (new TicketTierResource($updatedTier))->response();
        } catch (InvalidTicketTierUpdateException $e) {
            // --- CATCH THE SPECIFIC EXCEPTION ---
            // We caught our custom exception. We know exactly what went wrong.
            // Return a 422 error with the specific message from the exception.
            return response()->json([
                'message' => $e->getMessage(),
                'errors' => [
                    // You can associate the error with a specific field for the frontend.
                    'max_quantity' => [$e->getMessage()]
                ]
            ], 422); // 422 Unprocessable Entity
            
        } catch (ModelNotFoundException $e) {
            return response()->json(['message' => 'The requested resource could not be found.'], 422);
        } catch (\Exception $e) {
            Log::error("Failed to update ticket tier {$ticketTier->id}", ['error' => $e->getMessage()]);
            return response()->json(['error' => 'An error occurred while updating the ticket tier.'], 500);
        }
    }

    /**
     * Delete a ticket tier.
     */
    public function destroy(TicketTier $ticketTier): JsonResponse
    {
        try{
            $this->authorize('delete', $ticketTier);
            
            $this->ticketTierManager->deleteTier($ticketTier);
            
            return response()->json(null, 204);
        }  catch (ModelNotFoundException $e) {
            return response()->json(['message' => 'The requested resource could not be found.'], 422);
        }
    }

    /**
     * [PUT /services/{service}/ticket-tiers]
     * Replaces all ticket tiers for a service with the provided set (batch update).
     */
    public function batchUpdate(Request $request, BookableService $service): JsonResponse
    {
        $this->authorize('update', $service);
        
        $validated = $request->validate([
            'tiers' => 'present|array', // 'present' ensures the key exists, even if it's an empty array (to delete all tiers).
            'tiers.*.id' => 'sometimes|integer|exists:ticket_tiers,id',
            'tiers.*.name' => 'required|string|max:255',
            'tiers.*.base_price' => 'required|numeric|min:0',
            'tiers.*.min_quantity' => 'required|integer|min:0',
            'tiers.*.max_quantity' => 'required|integer|min:0',
        ]);
        
        try {
            $reconciledTiers = $this->ticketTierManager->reconcileTiers($service, $validated['tiers']);
            return TicketTierResource::collection($reconciledTiers)->response();
        } catch (ModelNotFoundException $e) {
            return response()->json(['error' => 'One or more ticket IDs provided do not belong to this service.'], 422);
        } catch (\Exception $e) {
            Log::error("Failed to batch update ticket tiers for service {$service->id}", ['error' => $e->getMessage()]);
            return response()->json(['error' => 'An error occurred while updating the ticket tiers.'], 500);
        }
    }

    /**
     * [PUT /ticket-tiers/reorder]
     * Handles the batch re-ordering of ticket tiers.
     */
    public function reorder(Request $request, BookableService $service): JsonResponse
    {
        $validated = $request->validate([
            'ordered_tier_ids' => [
                'required',
                'array',
                'min:1',
                Rule::exists('ticket_tiers', 'id')->where(function ($query) use ($service) {
                    $query->where('bookable_service_id', $service->id)
                          ->where('tenant_id', $this->tenantManager->getCurrentTenantId());
                }),
            ],
            'ordered_tier_ids.*' => 'integer',
        ]);

        try {
            $this->ticketTierManager->updateOrder(
                $validated['ordered_tier_ids'],
                $this->tenantManager->getCurrentTenantId(),
                $service->id
            );
            $paginatedTiers = $service->ticketTiers()
                ->whereIn('id', $validated['ordered_tier_ids'])
                ->orderBy('order_column')
                ->paginate(
                    perPage: $request->input('per_page', 15),
                    columns: ['*'],
                    pageName: 'page',
                    page: $request->input('page', 1)
                );
            return TicketTierResource::collection($paginatedTiers)->response()->setStatusCode(200);

        } catch (AuthorizationException $e) {
            return response()->json(['error' => $e->getMessage()], 403);
        } catch (\Exception $e) {
            Log::error("Failed to reorder ticket tiers", ['error' => $e->getMessage()]);
            return response()->json(['error' => 'An unexpected error occurred.'], 500);
        }
    }
}