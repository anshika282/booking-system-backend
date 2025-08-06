<?php

namespace App\Http\Controllers;

use App\Models\PricingRule;
use Illuminate\Http\Request;
use App\Models\BookableService;
use Illuminate\Http\JsonResponse;
use App\Services\PricingRuleManager;
use App\Http\Resources\PricingRuleResource;
use App\Http\Requests\PatchPricingRuleRequest;
use App\Http\Requests\StorePricingRuleRequest;
use App\Http\Requests\UpdatePricingRuleRequest;
use App\Http\Requests\ReorderPricingRulesRequest;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;

class PricingRuleController extends Controller
{
    use AuthorizesRequests;

    protected PricingRuleManager $pricingRuleManager;

    public function __construct(PricingRuleManager $pricingRuleManager)
    {
        $this->pricingRuleManager = $pricingRuleManager;
    }

    /**
     * [GET] /services/{service}/pricing-rules
     * List all pricing rules for a service, with pagination.
     */
    public function index(Request $request, BookableService $service): JsonResponse
    {
        $this->authorize('view', $service);

        $request->validate([
            'per_page' => 'sometimes|integer|min:1|max:100'
        ]);
        
        $perPage = $request->input('per_page', 15);

        $rules = $service->pricingRules()
                         ->orderBy('priority', 'asc')
                         ->paginate($perPage);
        
        return PricingRuleResource::collection($rules)->response();
    }

    /**
     * [POST] /services/{service}/pricing
     * Store a new pricing rule.
     */
    public function store(StorePricingRuleRequest $request, BookableService $service): JsonResponse
    {
        $this->authorize('update', $service); // If user can update the service, they can add rules.
        $rule = $this->pricingRuleManager->createRule($service, $request->validated());
        return (new PricingRuleResource($rule))->response()->setStatusCode(201);
    }

    /**
     * [GET] /pricing/{rule}
     * Show a single pricing rule.
     */
    public function show(BookableService $service, PricingRule $rule): JsonResponse
    {
        $this->authorize('view', $rule);
        return (new PricingRuleResource($rule))->response();
    }

    /**
     * [PUT] /pricing/{rule}
     * Update a pricing rule.
     */
    public function update(UpdatePricingRuleRequest $request,BookableService $service,  PricingRule $rule): JsonResponse
    {
        $this->authorize('update', $rule);
        $rule = $this->pricingRuleManager->updateRule($rule, $request->validated());
        return (new PricingRuleResource($rule))->response();
    }

    /**
     * [PATCH] /pricing-rules/{rule}
     * Partially updates a pricing rule with only editable values (dates and amounts).
     */
    public function patch(PatchPricingRuleRequest $request,BookableService $service, PricingRule $rule): JsonResponse
    {
        $this->authorize('update', $rule);
        
        // We still use our "Read-Modify-Write" service method for safe JSON merging.
        // The PatchPricingRuleRequest ensures only the allowed fields get through.
        $rule = $this->pricingRuleManager->updateRule($rule, $request->validated());
        
        return (new PricingRuleResource($rule))->response();
    }

    /**
     * [DELETE] /pricing/{rule}
     * Delete a pricing rule.
     */
    public function destroy(BookableService $service, PricingRule $rule): JsonResponse
    {
        $this->authorize('delete', $rule);
        $this->pricingRuleManager->deleteRule($rule);
        return response()->json(null, 204);
    }

    /**
     * [PATCH] /services/{service}/pricing-rules/reorder
     * Reorders the priority of all pricing rules for a service.
     */
    public function reorder(ReorderPricingRulesRequest $request, BookableService $service): JsonResponse
    {
        // Authorize that the user can update the parent service.
        $this->authorize('update', $service);
        
        try {
            $this->pricingRuleManager->reorderRules(
                $service,
                $request->validated()['ordered_rules']
            );

            // On success, a simple success message is sufficient.
            return response()->json(['message' => 'Pricing rule priorities updated successfully.']);

        } catch (\Illuminate\Auth\Access\AuthorizationException $e) {
            // This catches the error if the service manager finds a mismatched rule ID.
            return response()->json(['error' => $e->getMessage()], 403);
        } catch (\Exception $e) {
            \Log::error("Failed to reorder pricing rules for service {$service->id}", ['error' => $e->getMessage()]);
            return response()->json(['error' => 'An unexpected error occurred.'], 500);
        }
    }
}