<?php

namespace App\Http\Requests;

use Illuminate\Validation\Rule;
use Illuminate\Foundation\Http\FormRequest;

class StoreBookableServiceRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        // Authorization is handled by the auth:api and tenant.check middleware.
        return false;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'name' => 'required|string|max:255',
            'duration_minutes' => 'required|integer|min:1',
            'capacity_consumption_mode' => ['required', Rule::in(['per_ticket', 'per_booking'])],
            'slot_selection_mode' => ['required', Rule::in(['list_all', 'find_next'])],
            'booking_window_min_days' => 'required|integer|min:1',
            'booking_window_max_days' => 'required|integer|max:30|min:' . ($this->booking_window_min_days ?? 1),
            
            // Field to determine the service type
            'service_type' => ['required', Rule::in(['ticketed_event', 'appointment'])],

            // --- Conditional rules based on service_type ---

            // Fields for 'ticketed_event'
            'venue_name' => 'required_if:service_type,ticketed_event|string|max:255',
            'requires_waiver' => 'required_if:service_type,ticketed_event|boolean',

            // Fields for 'appointment'
            'buffer_time_minutes' => 'required_if:service_type,appointment|integer|min:0',
            'requires_provider' => 'required_if:service_type,appointment|boolean',
        ];
    }
}
