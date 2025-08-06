<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateOperatingHoursRequest extends FormRequest
{
    // ... authorize() method is the same ...

    public function rules(): array
    {
        return [
            // This field is now optional, but if present, must meet the criteria.
            'generate_slots_for_days' => 'sometimes|integer|min:1|max:90', // 'sometimes' validates only if present. Max 90 is a safety cap.

            'hours' => 'required|array|min:7|max:7',
            'hours.*.day_of_week' => 'required|integer|between:0,6',
            'hours.*.is_enabled' => 'required|boolean',
            'hours.*.open_time' => 'required_if:hours.*.is_enabled,true|date_format:H:i',
            'hours.*.close_time' => [
                'required_if:hours.*.is_enabled,true',
                'date_format:H:i',
                'after:hours.*.open_time', 
            ],
        ];
    }
}
