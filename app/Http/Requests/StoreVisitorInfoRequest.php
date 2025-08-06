<?php
namespace App\Http\Requests;
use Illuminate\Foundation\Http\FormRequest;

class StoreVisitorInfoRequest extends FormRequest
{
    public function authorize(): bool { return true; }

    public function rules(): array
    {
        return [
            'name' => 'required|string|max:255',
            'email' => 'required|email|max:255',
            'phone_number' => 'required|string|max:50|unique:customers,phone_number',
            'billing_address_1' => 'nullable|string|max:255',
            'billing_city' => 'nullable|string|max:100',
            'billing_state' => 'nullable|string|max:100',
            'billing_postal_code' => 'nullable|string|max:20',
            // Add other customer fields as needed
        ];
    }
}