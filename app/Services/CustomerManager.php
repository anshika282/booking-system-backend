<?php

namespace App\Services;

use App\Models\Customers;

class CustomerManager
{
    /**
     * Finds an existing customer by their phone number for a specific tenant,
     * or creates a new one if not found.
     *
     * @param array $customerData The validated customer details.
     * @return Customer
     */
    public function findOrCreateCustomer(array $customerData): Customers
    {
        // The unique key for a returning customer is their phone number scoped to the tenant.
        // firstOrCreate is an atomic and efficient way to handle this.
        $customer = Customers::firstOrCreate(
            [
                'phone_number' => $customerData['phone_number'],
            ],
            [
                // These values are only used if a NEW customer is being created.
                'name' => $customerData['name'],
                'email' => $customerData['email'],
                // Add billing address fields if they are provided
                'billing_address_line_1' => $customerData['billing_address_line_1'] ?? null,
                'billing_city' => $customerData['billing_city'] ?? null,
                'billing_postal_code' => $customerData['billing_postal_code'] ?? null,
                'billing_country' => $customerData['billing_country'] ?? null,
            ]
        );

        return $customer;
    }
}