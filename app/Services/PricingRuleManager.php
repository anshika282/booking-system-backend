<?php

namespace App\Services;

use App\Models\BookableService;
use App\Models\PricingRule;

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
        // The validated $data from the PATCH request contains only the keys the user wants to change.
        // For standard columns (like 'name', 'priority'), this is fine.
        // For JSON columns, if the key ('conditions' or 'price_modification') is present,
        // we should treat it as the NEW, COMPLETE state for that object.
        
        // Eloquent's update() method already does this replacement behavior correctly.
        // The previous merge logic was the source of the problem.
        // By removing it, we fix the bug.
        
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