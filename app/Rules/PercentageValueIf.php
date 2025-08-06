<?php

namespace App\Rules;

use Closure;
use Illuminate\Contracts\Validation\DataAwareRule;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Support\Arr;

/**
 * Validates that a value does not exceed a max, but only if other fields have specific values.
 * This rule is "DataAware", meaning it can access all other validated data.
 */
class PercentageValueIf implements ValidationRule, DataAwareRule
{
    /**
     * All of the data under validation.
     *
     * @var array<string, mixed>
     */
    protected $data = [];

    protected string $dependentTypeField;
    protected string $expectedTypeValue;
    protected string $dependentModeField;
    protected string $expectedModeValue;
    protected int $maxValue;

    /**
     * Create a new rule instance.
     *
     * @param string $dependentTypeField e.g., 'price_modification.type'
     * @param string $expectedTypeValue e.g., 'total_amount_discount'
     * @param string $dependentModeField e.g., 'price_modification.calculation_mode'
     * @param string $expectedModeValue e.g., 'percentage'
     * @param int $maxValue e.g., 100
     */
    public function __construct(
        string $dependentTypeField, 
        string $expectedTypeValue, 
        string $dependentModeField,
        string $expectedModeValue,
        int $maxValue = 100
    )
    {
        $this->dependentTypeField = $dependentTypeField;
        $this->expectedTypeValue = $expectedTypeValue;
        $this->dependentModeField = $dependentModeField;
        $this->expectedModeValue = $expectedModeValue;
        $this->maxValue = $maxValue;
    }

    /**
     * Set the data under validation.
     *
     * @param  array<string, mixed>  $data
     */
    public function setData(array $data): static
    {
        $this->data = $data;
        return $this;
    }

    /**
     * Run the validation rule.
     *
     * @param  \Closure(string): \Illuminate\Translation\PotentiallyTranslatedString  $fail
     */
    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        // Use Laravel's Arr::get helper to safely access nested array data.
        $type = Arr::get($this->data, $this->dependentTypeField);
        $mode = Arr::get($this->data, $this->dependentModeField);

        // Only apply the validation if all conditions are met.
        if ($type === $this->expectedTypeValue && 
            $mode === $this->expectedModeValue) {
            
            if (!is_numeric($value) || $value > $this->maxValue) {
                $fail("The :attribute cannot exceed {$this->maxValue} when calculation mode is 'percentage'.");
            }
        }
    }
}