<?php

namespace App\Http\Requests;

use Illuminate\Validation\Rule;
use App\Rules\BelongsToService;
use Illuminate\Foundation\Http\FormRequest;

class UpdatePricingRuleRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        // We need the parent service to scope the 'exists' rule for tier IDs.
        // We must fetch it from the {rule} parameter's relationship.
        $service = $this->route('rule')->bookableService;
        if (!$service) {
            // This is a safeguard, but an error should be thrown if a rule
            // somehow doesn't have a service.
            return [];
        }

        return [
            // --- Top-Level Fields ---
            // 'sometimes' allows for partial updates. 'required' ensures if the key is present, it's not empty.
            'name' => 'sometimes|required|string|max:255',
            'type' => 'sometimes|required|string|max:255',
            'active' => 'sometimes|required|boolean',
            'priority' => 'sometimes|required|integer',
            'category' => ['sometimes', 'required', 'string', Rule::in(['base_price_adjustment', 'discount'])],
            'is_stackable' => 'sometimes|required|boolean',

            // --- Conditions JSON ---
            // 'nullable' allows sending an empty conditions object to clear it.
            'conditions' => 'sometimes|nullable|array',
            'conditions.type' => ['required_with:conditions', 'string', Rule::in(['date', 'ticket_quantity'])],
            'conditions.from_date' => 'nullable|date_format:Y-m-d',
            'conditions.to_date' => 'nullable|date_format:Y-m-d|after_or_equal:conditions.from_date',
            'conditions.days_of_week' => 'nullable|array',
            'conditions.days_of_week.*' => 'integer|between:0,6',
            'conditions.specific_dates' => 'nullable|array',
            'conditions.specific_dates.*' => 'date_format:Y-m-d',
            'conditions.ticket_tier_id' => ['nullable', 'integer', new BelongsToService($service)],
            'conditions.min_quantity' => 'nullable|integer|min:1',


            // --- Price Modification JSON ---
            'price_modification' => 'sometimes|required|array',
            'price_modification.type' => ['sometimes', 'required', 'string', Rule::in(['set_fixed_price', 'total_amount_discount'])],
            
            // Rules for 'set_fixed_price'
            'price_modification.tiers' => ['sometimes', 'array'],
            'price_modification.tiers.*.ticket_tier_id' => ['required', 'integer', new BelongsToService($service)],
            'price_modification.tiers.*.value' => 'required|numeric|min:0',

            // Rules for 'buy_x_get_y_free'
           

            // Rules for 'total_amount_discount'
            'price_modification.calculation_mode' => ['sometimes', Rule::in(['percentage', 'fixed'])],
            'price_modification.amount' => [
                'sometimes',
                'numeric',
                'min:0',
                Rule::when($this->input('price_modification.calculation_mode') === 'percentage', ['max:100']),
            ],
        ];
    }
}