<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreTicketTierRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'name' => 'required|string|max:255',
            'base_price' => 'required|numeric|min:0',
            'min_quantity' => 'required|integer|min:0',
            'max_quantity' => 'required|integer|min:' . ($this->min_quantity ?? 0),
            'order_column' => 'sometimes|integer',
        ];
    }
}