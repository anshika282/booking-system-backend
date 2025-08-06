<?php

namespace App\Http\Controllers;

use App\Http\Resources\BookingResource;
use App\Http\Resources\CustomerResource;
use App\Models\Customer;
use App\Services\CustomerQueryService;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class CustomerController extends Controller
{
    use AuthorizesRequests;

    protected CustomerQueryService $customerQueryService;

    public function __construct(CustomerQueryService $customerQueryService)
    {
        $this->customerQueryService = $customerQueryService;
    }

    /**
     * [GET] /customers
     * Display a paginated list of the tenant's customers.
     */
    public function index(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'search' => 'sometimes|string|max:100',
            'per_page' => 'sometimes|integer|min:1|max:100',
        ]);
        
        $customers = $this->customerQueryService->getCustomersForTenant(
            $validated,
            $validated['per_page'] ?? 15
        );
        
        return CustomerResource::collection($customers)->response();
    }

    /**
     * [GET] /customers/{customer}
     * Display a specific customer's details and their booking history with this tenant.
     */
    public function show(Customer $customer): JsonResponse
    {
        // Authorize that this customer has actually booked with the current tenant.
        $this->authorize('view', $customer);
        
        $data = $this->customerQueryService->getCustomerDetailsForTenant($customer);

        // We can't use a resource directly because our data is a mix of the model and summary.
        // So we build the response manually.
        return response()->json([
            'data' => [
                'customer' => new CustomerResource($data['customer']),
                'tenant_summary' => $data['summary'],
                // Return their bookings with this tenant, paginated.
                'bookings' => BookingResource::collection($data['customer']->bookings()->paginate(5)),
            ]
        ]);
    }
}