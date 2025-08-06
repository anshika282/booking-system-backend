<?php

namespace App\Rules;

use App\Models\BookableService;
use Closure;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Support\Facades\DB;

class BelongsToService implements ValidationRule
{
    protected BookableService $service;

    /**
     * Create a new rule instance.
     *
     * @param \App\Models\BookableService $service
     */
    public function __construct(BookableService $service)
    {
        $this->service = $service;
    }

    /**
    * Run the validation rule.
    *
    * @param \Closure(string): \Illuminate\Translation\PotentiallyTranslatedString $fail
    */
    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        // We use the query builder for a simple and fast existence check.
        $exists = DB::table('ticket_tiers')
            ->where('bookable_service_id', $this->service->id)
            ->where('id', $value)
            ->exists();

        if (!$exists) {
            // The error message is generic but clear.
            $fail('The selected :attribute is invalid or does not belong to this service.');
        }
    }
}