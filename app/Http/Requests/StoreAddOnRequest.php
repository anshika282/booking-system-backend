<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreAddOnRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $serviceId = $this->route('service')->id;
        return [
            'name' => [
                'required',
                'string',
                'max:255',
                // This rule ensures the name is unique for this specific service
                Rule::unique('addons')->where('bookable_service_id', $serviceId),
            ],
            'is_included_in_ticket' => 'required|boolean',
            
            // The 'price' field is now only required if 'is_included_in_ticket' is false.
            'price' => [
                'required_if:is_included_in_ticket,false',
                'nullable', // It must be allowed to be null if the add-on is included.
                'numeric',
                'min:0',
            ],

            'type' => ['required', 'string', Rule::in(['per_booking', 'per_person'])],

        ];
    }
}