<?php

namespace App\Policies;

use App\Models\PricingRule;
use App\Models\User;

class PricingRulePolicy
{
    /**
     * Determine whether the user can view the model.
     */
    public function view(User $user, PricingRule $rule): bool
    {
        return $user->tenant_id === $rule->tenant_id;
    }

    /**
     * Determine whether the user can update the model.
     */
    public function update(User $user, PricingRule $rule): bool
    {
        return $user->tenant_id === $rule->tenant_id;
    }

    /**
     * Determine whether the user can delete the model.
     */
    public function delete(User $user, PricingRule $rule): bool
    {
        return $user->tenant_id === $rule->tenant_id;
    }
}