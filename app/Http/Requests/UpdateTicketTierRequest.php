<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class UpdateTicketTierRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        

        return [
            // 'sometimes' ensures we only validate fields that are present in the PATCH request.
            // 'required' ensures that if the key is present, it is not empty.
            'name' => 'sometimes|required|string|max:255',
            'base_price' => 'sometimes|required|numeric|min:0',
            'min_quantity' => 'sometimes|required|integer|min:0',
            'max_quantity' => [
                'sometimes',
                'required',
                'integer',
                'min:0'
            ],
            'order_column' => 'sometimes|integer',
        ];
    }
}