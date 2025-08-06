<?php

namespace App\Http\Requests;

use App\Rules\BelongsToService;
use Illuminate\Validation\Rule;
use App\Rules\RequiresOnlyOneOf;
use Illuminate\Foundation\Http\FormRequest;

class PatchPricingRuleRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $rule = $this->route('rule');
        $service = $rule->bookableService;

        if (!$service) {
            // Safeguard, should not happen in normal operation.
            return [];
        }

        return [
              'conditions' => [
                'sometimes',
                'nullable',
                'array',
                new RequiresOnlyOneOf(),
            ],
            
          // We can still keep the individual field validation
            'conditions.type' => 'required_with:conditions|string',
            'conditions.days_of_week' => 'nullable|array',
            'conditions.days_of_week.*' => 'integer|between:0,6',
            'conditions.from_date' => 'nullable|date_format:Y-m-d',
            // Add 'required_with' to ensure if from_date exists, to_date also exists
            'conditions.to_date' => 'required_with:conditions.from_date|nullable|date_format:Y-m-d|after_or_equal:conditions.from_date',
            'conditions.specific_dates' => 'nullable|array',
            'conditions.specific_dates.*' => 'date_format:Y-m-d',

            // --- Price Modification Validation ---
            'price_modification' => 'sometimes|required|array',
            'price_modification.type' => 'sometimes|required|string', // And other rules...

            // == Rules for 'set_fixed_price' type ==
            'price_modification.tiers' => ['sometimes', 'array'],

            'price_modification.tiers.*.ticket_tier_id' => [
                'required',
                'integer',
                // We are now validating that EACH ticket_tier_id in the array
                // actually belongs to the service this pricing rule is attached to.
                new BelongsToService($service) 
            ],
            'price_modification.tiers.*.value' => 'required|numeric|min:0',

        ];
    }
}