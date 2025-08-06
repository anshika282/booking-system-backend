<?php

namespace App\Http\Controllers;

use App\Models\BookableService;
use App\Models\Tenant;
// You would create a dedicated, public-safe API Resource
use App\Http\Resources\PublicServiceResource; 

class PublicBookingController extends Controller
{
    /**
     * Show a single service's public booking information.
     *
     * Thanks to Route-Model Binding, Laravel has already found the correct Tenant
     * and the correct BookableService, and has verified their relationship.
     *
     * @param Tenant $tenant
     * @param BookableService $service
     * @return \Illuminate\Http\JsonResponse
     */
    public function showService(Tenant $tenant, BookableService $service)
    {
        // At this point, you are GUARANTEED that:
        // 1. A tenant with the given UUID exists.
        // 2. A service with the given ID exists.
        // 3. The service belongs to that tenant.
        // If any of these were false, Laravel would have already sent a 404 response.

        // You can now safely load the data needed for the public booking page.
        $service->load(['ticketTiers', 'addons', 'coupons', 'availabilitySlots', 
                        'operatingHours', 'pricingRules']);

        // Use a dedicated resource to make sure you only expose public-safe data.
        return (new PublicServiceResource($service))->response();
    }
}   