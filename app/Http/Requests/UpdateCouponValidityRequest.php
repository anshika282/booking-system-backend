<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class UpdateCouponValidityRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'conditions' => 'required|array',
            // Ensure only the date-related fields are present and valid
            'conditions.valid_from' => 'nullable|date',
            'conditions.valid_to' => 'nullable|date|after_or_equal:conditions.valid_from',
            'conditions.days_of_week' => 'nullable|array',
            'conditions.days_of_week.*' => 'integer|between:0,6',
            'conditions.specific_dates' => 'nullable|array',
            'conditions.specific_dates.*' => 'date_format:Y-m-d',
        ];
    }
}