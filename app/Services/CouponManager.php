<?php

namespace App\Services;

use App\Models\Coupon;
use App\Models\BookableService;

class CouponManager
{
    

    public function createCoupon(BookableService $service, array $data): Coupon
    {
        // The coupon is now a child of the service.
        // We use the relationship to create it, which automatically sets
        // the bookable_service_id and tenant_id.
        $data['tenant_id'] = $service->tenant_id;
        return $service->coupons()->create($data);
    }

    public function updateCoupon(Coupon $coupon, array $data): Coupon
    {
        $coupon->update($data);
        return $coupon->fresh();
    }

    public function deleteCoupon(Coupon $coupon): bool
    {
        // Business Rule: A tenant might not want to delete a coupon that has been used.
        // A "soft delete" or an "active" flag is often better. We have an 'active' flag.
        // For this implementation, we will allow hard deletion.
        return $coupon->delete();
    }

     /**
     * Updates an existing coupon, correctly merging nested JSON data.
     */
    // public function updateCoupon(Coupon $coupon, array $data): Coupon
    // {
    //     if (isset($data['conditions'])) {
    //         $data['conditions'] = array_merge($coupon->conditions ?? [], $data['conditions']);
    //     }
    //     if (isset($data['effects'])) {
    //         $data['effects'] = array_merge($coupon->effects ?? [], $data['effects']);
    //     }

    //     $coupon->update($data);
    //     return $coupon->fresh();
    // }
}