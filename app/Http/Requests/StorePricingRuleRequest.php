<?php

namespace App\Http\Requests;

use Illuminate\Validation\Rule;
use App\Rules\PercentageValueIf;
use App\Rules\BelongsToService; 
use Illuminate\Foundation\Http\FormRequest;

class StorePricingRuleRequest extends FormRequest
{
    
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
         $service = $this->route('service');
        return [
            // --- Top-Level Rule Fields ---
            'name' => 'required|string|max:255',
             // This validates the main 'type' column for the pricing_rules table itself.
            'type' => 'required|string|max:255', 
            'active' => 'required|boolean',
            'priority' => 'required|integer',
            'category' => ['required', 'string', Rule::in(['base_price_adjustment', 'discount'])],
            'is_stackable' => 'required|boolean',

            // --- Conditions JSON Validation ---
            'conditions' => 'nullable|array',
            'conditions.type' => ['required_with:conditions', 'string', Rule::in(['date', 'ticket_quantity'])], // Allow 'date' or 'ticket_quantity' types
            
            // 1. Date-based condition rules (only apply if type is 'date')
            'conditions.from_date' => 'sometimes|required_if:conditions.type,date|date_format:Y-m-d',
            'conditions.to_date' => 'sometimes|required_if:conditions.type,date|date_format:Y-m-d|after_or_equal:conditions.from_date',
            'conditions.days_of_week' => 'sometimes|required_if:conditions.type,date|array',
            'conditions.days_of_week.*' => 'integer|between:0,6', // Each item in the array must be a valid day
            'conditions.specific_dates' => 'sometimes|required_if:conditions.type,date|array',
            'conditions.specific_dates.*' => 'date_format:Y-m-d',

            // 2. Ticket Quantity condition rules
            'conditions.ticket_tier_id' => [
                'required_if:conditions.type,ticket_quantity',
                'integer',
                 new BelongsToService($service),
            ],
            'conditions.min_quantity' => 'required_if:conditions.type,ticket_quantity|integer|min:1',

            // --- Price Modification JSON Validation ---
            'price_modification' => 'required|array',
            'price_modification.type' => ['required', 'string', Rule::in(['set_fixed_price', 'percentage_change', 'fixed_amount_change', 'total_amount_discount'])],
            
            // Validation for the 'value' field based on the modification type
            'price_modification.value' => [
                'required_if_in:price_modification.type,set_fixed_price,percentage_change,fixed_amount_change', 
                'numeric',
                'sometimes' => function ($attribute, $value, $fail) {
                                if ($this->input('price_modification.type') === 'percentage_change' && $value > 100) {
                                    $fail('The ' . $attribute . ' must not be greater than 100 when the type is percentage_change.');
                                }
                            },
                ],
            'price_modification.value' => 'prohibited_if:price_modification.type,percentage_change|max:0|min:-100',

          

            //Rules for our 'total_amount_discount' type
            'price_modification.calculation_mode' => [
                'required_if:price_modification.type,total_amount_discount',
                Rule::in(['percentage', 'fixed']),
            ],
            'price_modification.amount' => [
                'required_if:price_modification.type,total_amount_discount',
                'numeric',
                'min:0',
             Rule::when($this->input('price_modification.calculation_mode') === 'percentage', ['max:100']),
            ],

            // Optional field to target specific ticket tiers
            'price_modification.tiers' => 'sometimes|array',
            // Check that each ID in the 'tiers' array actually exists in the ticket_tiers table
            // and belongs to the service we're editing.
            'price_modification.tiers.*.ticket_tier_id' => [
                'required',
                'integer',
                new BelongsToService($service) // <-- REUSE THE NEW RULE AGAIN
            ],
            'price_modification.tiers.*.value' => 'required|numeric|min:0',
        ];
    }
}