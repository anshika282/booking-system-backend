<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use App\Services\TenantManager; // Assuming injection for future Tenant ID check

class StorePaymentConfigRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        // This is restricted by a Gate on the route (Owner-only), but we can add a secondary check.
        $user = $this->user();
        return $user && $user->role === 'owner';
    }

    /**
     * Get the validation rules that apply to the request.
     */
    public function rules(): array
    {
        // For scalability, the credentials validation is conditional based on the gateway type.
        return [
            // --- Top-level Validation ---
            'gateway_type' => ['required', 'string', Rule::in(['phonepe', 'stripe'])],
            'is_default' => 'required|boolean', // Always true for now, but scalable
            'credentials' => 'required|array',

            // --- PhonePe Conditional Validation ---
            'credentials.merchant_id' => [
                'required_if:gateway_type,phonepe', 
                'string', 
                'max:255',
            ],
            'credentials.salt_key' => [
                'required_if:gateway_type,phonepe', 
                'string', 
                'max:255',
            ],
            'credentials.salt_index' => [
                'required_if:gateway_type,phonepe', 
                'integer', 
                'min:1',
            ],
            
            // --- Stripe Conditional Validation (For Future) ---
            // 'credentials.publishable_key' => [
            //     'required_if:gateway_type,stripe', 
            //     'string', 
            //     'max:255',
            // ],
            // 'credentials.secret_key' => [
            //     'required_if:gateway_type,stripe', 
            //     'string', 
            //     'max:255',
            // ],
        ];
    }
}