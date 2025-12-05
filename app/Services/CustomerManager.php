<?php

namespace App\Services;

use App\Models\Tenant;
use App\Models\Customers;
use Illuminate\Support\Str;

class CustomerManager
{
    /**
     * Finds an existing customer by their phone number for a specific tenant,
     * or creates a new one if not found.
     *
     * @param array $customerData The validated customer details.
     * @return Customer
     */
    public function findOrCreateCustomer(array $customerData, Tenant $tenant): Customers
    {
        // The unique key for a returning customer is their phone number scoped to the tenant.
        // firstOrCreate is an atomic and efficient way to handle this.
        \Log::info('Finding or creating customer with phone: ' . $customerData['country']);
         $customer = Customers::updateOrCreate(
            [
                'phone_number' => $customerData['phone'], 
            ],
            [
                // These values are only used if a NEW customer is being created.
                'name' => $customerData['name'],
                'email' => $customerData['email'],
                'billing_address_line_1' => $customerData['address1'] ?? null,
                'billing_city' => $customerData['city'] ?? null,
                'billing_postal_code' => $customerData['postalCode'] ?? null,
                'billing_country' => $customerData['country'] ?? null,
                'is_placeholder' => false,
                'api_token' => hash('sha256', Str::random(60)),
            ]
        );
        \Log::info('Customer found or created: ' . $customer);

        return $customer;
    }
}