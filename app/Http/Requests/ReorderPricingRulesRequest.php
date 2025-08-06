<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class ReorderPricingRulesRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            // We expect a key 'ordered_rules' which must be an array.
            'ordered_rules' => 'required|array',
            // Each item in the array must be an integer (the rule ID).
            'ordered_rules.*' => 'required|integer|exists:pricing_rules,id',
        ];
    }
}