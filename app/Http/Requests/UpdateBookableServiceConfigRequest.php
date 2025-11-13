<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateBookableServiceConfigRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true; // Authorization is handled by the policy in the controller.
    }

    /**
     * Get the validation rules that apply to the request.
     * 'sometimes' is used to allow for partial updates - only validating fields that are present.
     * 'required' is used with 'sometimes' to ensure that if a key is present, it's not empty.
     *
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        $rules = [
            'booking_window_min_days' => 'sometimes|required|integer|min:0',
            'booking_window_max_days' => [
                'sometimes',
                'required',
                'integer',
                // Max days must be greater than or equal to the min days being sent,
                // or the existing min days if it's not being updated.
                'min:' . ($this->input('booking_window_min_days') ?? $this->route('service')->booking_window_min_days),
            ],
            'slot_selection_mode' => ['sometimes', 'required', Rule::in(['list_all', 'find_next'])],
            'default_capacity' => 'sometimes|required|integer|min:1',
            'requires_waiver' => 'sometimes|required|boolean',
            'location_id' => [
                'sometimes',
                'required',
                'integer',
            ],
             'login_flow_preference' => ['sometimes', 'required', Rule::in(['login_first', 'login_at_checkout', 'guest_only'])],
        ];

        // This is a dynamic rule to adjust the min value for booking_window_min_days
        // if booking_window_max_days is being updated without it.
        if ($this->has('booking_window_max_days') && !$this->has('booking_window_min_days')) {
            $rules['booking_window_min_days'] = 'sometimes|integer|max:' . $this->input('booking_window_max_days');
        }


        return $rules;
    }
}