<?php

namespace App\Services;

use App\Models\PricingRule;
use App\Models\BookableService;
use Illuminate\Support\Facades\DB;

class PricingRuleManager
{
    /**
     * Creates a new pricing rule for a given service.
     *
     * @param BookableService $service The service this rule belongs to.
     * @param array $data The validated data for the new rule.
     * @return PricingRule
     */
    public function createRule(BookableService $service, array $data): PricingRule
    {
        // Associate the rule with the parent service and its tenant for data integrity.
        $data['bookable_service_id'] = $service->id;
        $data['tenant_id'] = $service->tenant_id;

        return PricingRule::create($data);
    }

   /**
     * Updates an existing pricing rule.
     * This version REPLACES JSON columns instead of merging them to ensure data integrity.
     *
     * @param PricingRule $rule The rule to update.
     * @param array $data The validated data containing the changes.
     * @return PricingRule
     */
    public function updateRule(PricingRule $rule, array $data): PricingRule
    {
        \Log::info('Updating pricing rule', ['rule_id' => $rule->id, 'data' => $data]);
        // Handle JSON columns by merging, not replacing.
        if (isset($data['conditions'])) {
            // Read the existing conditions, merge the new data, and overwrite the key in the payload.
            $data['conditions'] = array_merge($rule->conditions ?? [], $data['conditions']);
        }

        if (isset($data['price_modification'])) {
            // Read the existing modification, merge the new data, and overwrite the key in the payload.
            $data['price_modification'] = array_merge($rule->price_modification ?? [], $data['price_modification']);
        }
        
        // Now, the $data array contains all the original JSON data plus the incoming changes.
        // It is now safe to perform a standard update.
        $rule->update($data);

        return $rule->fresh();
    }

    /**
     * Deletes a pricing rule.
     *
     * @param PricingRule $rule
     * @return bool
     */
    public function deleteRule(PricingRule $rule): bool
    {
        return $rule->delete();
    }

    /**
     * Updates the priority of multiple pricing rules in a single, atomic operation.
     *
     * @param BookableService $service The service whose rules are being reordered.
     * @param array $orderedRuleIds An array of pricing rule IDs in their new desired order.
     * @return bool
     */
    public function reorderRules(BookableService $service, array $orderedRuleIds): bool
    {
        // We use a transaction to ensure that if any part of the update fails,
        // the entire operation is rolled back, preventing a partially-ordered state.
        DB::transaction(function () use ($service, $orderedRuleIds) {
            // First, verify that all incoming IDs actually belong to the specified service.
            // This is a critical security and data integrity check.
            $ownedRulesCount = $service->pricingRules()->whereIn('id', $orderedRuleIds)->count();
            if ($ownedRulesCount !== count($orderedRuleIds)) {
                // If the counts don't match, it means the client sent an ID for a rule
                // that doesn't belong to this service. Abort the operation.
                throw new \Illuminate\Auth\Access\AuthorizationException('One or more pricing rules do not belong to this service.');
            }

            // Loop through the ordered array and update the 'priority' for each rule.
            // The array index (0, 1, 2...) becomes the new priority.
            // We use a simple loop with individual updates here. For hyper-performance on
            // thousands of rules, a single raw SQL CASE statement could be used, but this
            // approach is much more readable, maintainable, and fires Eloquent events.
            foreach ($orderedRuleIds as $index => $ruleId) {
                PricingRule::where('id', $ruleId)
                           ->where('bookable_service_id', $service->id) // Extra safety check
                           ->update(['priority' => $index]);
            }
        });

        return true;
    }
}