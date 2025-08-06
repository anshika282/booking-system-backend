<?php

namespace App\Http\Controllers;

use App\Models\Coupon;
use Illuminate\Http\Request;
use App\Models\BookableService;
use App\Services\CouponManager;
use App\Services\TenantManager;
use Illuminate\Http\JsonResponse;
use App\Http\Resources\CouponResource;
use App\Http\Requests\StoreCouponRequest;
use App\Http\Requests\UpdateCouponRequest;
use App\Http\Requests\UpdateCouponValidityRequest;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;

class CouponController extends Controller
{
    use AuthorizesRequests;

    protected CouponManager $couponManager;
    protected TenantManager $tenantManager;

    public function __construct(CouponManager $couponManager, TenantManager $tenantManager)
    {
        $this->couponManager = $couponManager;
        $this->tenantManager = $tenantManager;
    }

    /**
     * Display a paginated listing of the resource.
     */
    public function index(BookableService $service, Request $request): JsonResponse
    {
        // Authorize that the user can perform actions for their service.
        $this->authorize('view', $service);
        // A policy for viewAny can be created, but checking the tenant context is sufficient here.
        $request->validate([
            'per_page' => 'sometimes|integer|min:1|max:100'
        ]);

        $perPage = $request->input('per_page', 15);

        $coupons = $service->coupons()->orderBy('created_at', 'desc')
                         ->paginate($perPage);
        
        // The collection() method on the resource automatically handles pagination.
        return CouponResource::collection($coupons)->response();
    }

    public function store(StoreCouponRequest $request, BookableService $service): JsonResponse
    {
        $this->authorize('update', $service);
        // No model to authorize yet, authorization happens implicitly via tenant check.
        $coupon = $this->couponManager->createCoupon($service,$request->validated());
        return (new CouponResource($coupon))->response()->setStatusCode(201);
    }

    public function show(BookableService $service, Coupon $coupon): JsonResponse
    {
        $this->authorize('view', [$coupon, $service]);
        return (new CouponResource($coupon))->response();
    }

    public function update(BookableService $service, UpdateCouponRequest $request, Coupon $coupon): JsonResponse
    {
         $this->authorize('update', [$coupon, $service]);
        $coupon = $this->couponManager->updateCoupon($coupon, $request->validated());
        return (new CouponResource($coupon))->response();
    }

     /**
     * [PATCH] /coupons/{coupon}/validity
     * Partially updates only the date-related conditions of a coupon.
     */
    public function patchValidity(UpdateCouponValidityRequest $request,BookableService $service, Coupon $coupon): JsonResponse
    {
        $this->authorize('delete', [$coupon, $service]); // Reuse the same update policy

        // Use the "Read-Modify-Write" pattern from our manager for safety
        $data = $request->validated();
        $newConditions = array_merge($coupon->conditions ?? [], $data['conditions']);
        
        $coupon->update(['conditions' => $newConditions]);

        return (new CouponResource($coupon->fresh()))->response();
    }

    public function destroy(BookableService $service, Coupon $coupon): JsonResponse
    {
        $this->authorize('delete', [$coupon, $service]);
        $this->couponManager->deleteCoupon($coupon);
        return response()->json(null, 204);
    }
}