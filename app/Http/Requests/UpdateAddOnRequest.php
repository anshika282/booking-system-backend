<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateAddOnRequest extends FormRequest
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
            'price' => 'sometimes|required|numeric|min:0',
            // If 'is_included_in_ticket' is in the payload, it must be a boolean.
            'is_included_in_ticket' => 'sometimes|required|boolean',
            'type' => ['sometimes', 'required', 'string', Rule::in(['per_booking', 'per_person'])],
        ];
    }
}