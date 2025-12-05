<?php
namespace App\Http\Requests;
use App\Models\Customers;
use App\Models\BookingIntent;
use Illuminate\Validation\Rule;
use Illuminate\Foundation\Http\FormRequest;

class StoreVisitorInfoRequest extends FormRequest
{
    public function authorize(): bool { return true; }

    public function rules(): array
    {
         $intent = $this->route('intent');
        $ignoreCustomerId = null;
        
        // 1. If we are NOT a guest, find the existing customer ID to ignore.
        // We look up the customer based on the PHONE from the payload.
        if (!$this->input('is_guest') && $this->input('phone')) {
            $customer = Customers::where('phone_number', $this->input('phone'))->first();

            // If a customer exists with this phone number, that's the ID we need to ignore.
            // This works for existing customers logging in AND new customers verifying
            // a phone number that was used in an old, abandoned session (to retrieve their old record).
            if ($customer) {
                $ignoreCustomerId = $customer->id;
            }
        }
        
        // As a fallback/secondary check, if the intent *already* has a customer_id, we use that.
        if ($ignoreCustomerId === null && $intent instanceof BookingIntent && $intent->customer_id) {
             $ignoreCustomerId = $intent->customer_id;
        }

        \Log::info('ignore id is :', [$ignoreCustomerId]);
        
        return [
            'name' => 'required|string|max:255',
            'email' => 'required|email|max:255',
            'phone' => [
                'required', 
                'string', 
                'max:50',
                // CRITICAL FIX: Only enforce unique check if we are NOT in a guest flow (is_guest: false)
                // AND ignore the current user's ID if one is attached to the intent.
                Rule::unique('customers', 'phone_number')->ignore($ignoreCustomerId)
            ],
            'address1' => 'nullable|string|max:255',
            'city' => 'nullable|string|max:100',
            'country' => 'nullable|string|max:2',
            'postalCode' => 'nullable|string|max:20',
            'is_guest' => 'required|boolean',
            // Add other customer fields as needed
        ];
    }
}