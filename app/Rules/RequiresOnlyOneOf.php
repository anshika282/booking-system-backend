<?php

namespace App\Rules;

use Closure;
use Illuminate\Contracts\Validation\DataAwareRule;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Support\Arr;

class RequiresOnlyOneOf implements ValidationRule, DataAwareRule
{
    /**
     * @var array<string, mixed>
     */
    protected $data = [];

    /**
     * The list of fields where only one is allowed.
     * @var array<int, string>
     */
    protected array $fields;

    /**
     * Create a new rule instance.
     *
     * @param string ...$fields
     */
    public function __construct(string ...$fields)
    {
        $this->fields = $fields;
    }

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
        // $value is the 'conditions' array itself.
        if (!is_array($value)) {
            return; // Another rule will handle this.
        }

        // This rule only cares about conditions of type 'date'.
        if (Arr::get($value, 'type') !== 'date') {
            return;
        }

        // --- THE CORE LOGIC ---
        
        $hasDateRange = !empty($value['from_date']) && !empty($value['to_date']);
        $hasDaysOfWeek = !empty($value['days_of_week']);
        $hasSpecificDates = !empty($value['specific_dates']);
        
        // Convert the booleans to integers (0 or 1) and sum them up.
        $definedConditionsCount = (int)$hasDateRange + (int)$hasDaysOfWeek + (int)$hasSpecificDates;

        if ($definedConditionsCount > 1) {
            $fail('The date condition is ambiguous. Please provide either a date range, specific dates, or days of the week, but not a combination.');
        }

        if ($definedConditionsCount === 0) {
            $fail('The date condition is incomplete. Please specify a date range, specific dates, or days of the week.');
        }
    }
}