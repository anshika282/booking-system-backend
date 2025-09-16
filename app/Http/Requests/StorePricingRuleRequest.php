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

     /**
     * Prepare the data for validation.
     * This method runs BEFORE the rules() method.
     */
    protected function prepareForValidation(): void
    {
        $conditions = $this->input('conditions', []);
        
        if (isset($conditions['type']) && $conditions['type'] === 'date') {
            $subType = $conditions['date_condition_sub_type'] ?? null;

            // Based on the sub-type, nullify the other date fields to prevent
            // old data from interfering with validation.
            if ($subType === 'range') {
                $conditions['specific_dates'] = null;
                $conditions['days_of_week'] = null;
            } elseif ($subType === 'single' || $subType === 'multiple') {
                $conditions['from_date'] = null;
                $conditions['to_date'] = null;
                $conditions['days_of_week'] = null;
            }
            // You can add a similar condition for 'days_of_week' if you implement it
            
        } else {
            // If the condition is not date-based, nullify all date fields.
            $conditions['from_date'] = null;
            $conditions['to_date'] = null;
            $conditions['specific_dates'] = null;
            $conditions['days_of_week'] = null;
        }

        // Merge the cleaned conditions back into the request data.
        $this->merge([
            'conditions' => $conditions,
        ]);
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
            'conditions.date_condition_sub_type' => ['required_if:conditions.type,date', 'nullable', 'string', Rule::in(['single', 'range', 'multiple'])],

            // These fields now depend on the 'date_condition_sub_type'
            'conditions.from_date' => 'required_if:conditions.date_condition_sub_type,range|nullable|date_format:Y-m-d',
            'conditions.to_date' => 'required_if:conditions.date_condition_sub_type,range|nullable|date_format:Y-m-d|after_or_equal:conditions.from_date',
            
            'conditions.specific_dates' => [
                'required_if:conditions.date_condition_sub_type,single,multiple',
                'nullable',
                'array'
            ],
            'conditions.specific_dates.*' => 'date_format:Y-m-d',

            // 2. Ticket Quantity condition rules
            'conditions.ticket_tier_id' => [
                'required_if:conditions.type,ticket_quantity',
                'nullable',
                'integer',
                 new BelongsToService($service),
            ],
            'conditions.min_quantity' => 'required_if:conditions.type,ticket_quantity|nullable|integer|min:1',

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
                'nullable',
                Rule::in(['percentage', 'fixed']),
            ],
            'price_modification.amount' => [
                'required_if:price_modification.type,total_amount_discount',
                'nullable',
                'numeric',
                'min:0',
             Rule::when($this->input('price_modification.calculation_mode') === 'percentage', ['max:100']),
            ],

            // Optional field to target specific ticket tiers
            // Rules for our 'set_fixed_price' type (Base Price Adjustment)
            'price_modification.tiers' => [
                'required_if:price_modification.type,set_fixed_price',
                'nullable', // This allows the field to be null when it's a discount rule
                'array',    // This rule will only run if the value is not null
            ],
            'price_modification.tiers.*.ticket_tier_id' => [
                'required_with:price_modification.tiers', // Only required if the tiers array is present
                'integer',
                new BelongsToService($service)
            ],
            'price_modification.tiers.*.value' => 'required_with:price_modification.tiers|numeric|min:0',
        ];
    }
}