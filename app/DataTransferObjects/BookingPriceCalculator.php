<?php

namespace App\Services;

use App\DataTransferObjects\PriceBreakdown;
use App\Models\BookableService;
use App\Models\Coupon;
use App\Models\PricingRule;
use Carbon\Carbon;
use Illuminate\Support\Collection;

class BookingPriceCalculator
{
    /**
     * The main public method to calculate the price for a booking selection.
     *
     * @param BookableService $service The service being booked.
     * @param string $date The date of the booking (YYYY-MM-DD).
     * @param array $selections Contains tickets, add_ons, and coupon_code.
     * @return PriceBreakdown The completed DTO with all calculations.
     */
    public function calculate(BookableService $service, string $date, array $selections): PriceBreakdown
    {
        dd('herr');
        $bookingDate = Carbon::parse($date);
        $activeRules = $service->pricingRules()->where('active', true)->orderBy('priority', 'asc')->get();
        
        $breakdown = new PriceBreakdown();
        
        // Phase 1 & 2: Establish Line Items and Subtotal based on base price adjustments
        $this->processLineItems($breakdown, $service, $activeRules, $bookingDate, $selections['tickets'] ?? []);

        // Proceed with discounts only if there is a subtotal to discount
        if ($breakdown->adjustedSubtotal > 0) {
            // Phase 3: Apply Automatic Discounts from Pricing Rules
            $this->applyAutomaticDiscounts($breakdown, $activeRules, $bookingDate);

            // Phase 4: Apply Explicit Coupon Discount
            if (!empty($selections['coupon_code'])) {
                $this->applyCouponDiscount($breakdown, $selections['coupon_code'], $bookingDate);
            }
        }
        
        // Phase 5: Add Add-on Costs
        $this->processAddOnCosts($breakdown, $service, $selections['add_ons'] ?? []);
        
        // Phase 6: Calculate the final grand total
        $breakdown->calculateFinalTotal();

        return $breakdown;
    }

    private function processLineItems(PriceBreakdown &$breakdown, BookableService $service, Collection $rules, Carbon $date, array $selectedTiers): void
    {
        $tierIds = array_column($selectedTiers, 'tier_id');
        if (empty($tierIds)) return;

        $baseTiers = $service->ticketTiers()->whereIn('id', $tierIds)->get()->keyBy('id');
        
        $adjustmentRules = $rules->where('category', 'base_price_adjustment');
        $appliedAdjustmentRule = null;

        foreach ($adjustmentRules as $rule) {
            if ($this->areConditionsMet($rule, collect($selectedTiers), $date)) {
                $appliedAdjustmentRule = $rule;
                break; // "First match wins" for base price adjustments
            }
        }

        foreach ($selectedTiers as $selection) {
            $tier = $baseTiers->get($selection['tier_id']);
            if (!$tier) continue;

            $basePrice = (float) $tier->base_price;
            $finalPrice = $basePrice;

            if ($appliedAdjustmentRule) {
                $mod = $appliedAdjustmentRule->price_modification;
                if ($mod['type'] === 'set_fixed_price') {
                    $modTier = collect($mod['tiers'])->firstWhere('ticket_tier_id', $tier->id);
                    if ($modTier) {
                        $finalPrice = (float) $modTier['value'];
                    }
                }
            }
            
            $quantity = (int) $selection['quantity'];
            if ($quantity <= 0) continue;

            $breakdown->lineItems->push([
                'ticket_tier_id' => $tier->id,
                'name' => $tier->name,
                'quantity' => $quantity,
                'unit_price' => $finalPrice,
                'subtotal' => $finalPrice * $quantity,
            ]);
        }
        
        $breakdown->baseSubtotal = $breakdown->lineItems->sum(fn($item) => $baseTiers->get($item['ticket_tier_id'])->base_price * $item['quantity']);
        $breakdown->adjustedSubtotal = $breakdown->lineItems->sum('subtotal');
    }
    
    private function applyAutomaticDiscounts(PriceBreakdown &$breakdown, Collection $rules, Carbon $date): void
    {
        $discountRules = $rules->where('category', 'discount');
        $nonStackableRuleApplied = false;

        foreach ($discountRules as $rule) {
            if ($nonStackableRuleApplied && !$rule->is_stackable) continue;
            
            if ($this->areConditionsMet($rule, $breakdown->lineItems, $date)) {
                $discountAmount = $this->calculateDiscountAmount($breakdown->adjustedSubtotal, $breakdown->lineItems, $rule);
                
                if ($discountAmount > 0) {
                    if (!$rule->is_stackable) {
                        // This is a non-stackable rule. It overrides all previous stackable ones.
                        $breakdown->appliedDiscounts = new Collection(); 
                        $nonStackableRuleApplied = true;
                    }
                    
                    $breakdown->appliedDiscounts->push([
                        'name' => $rule->name,
                        'amount' => round($discountAmount, 2),
                    ]);
                }
            }
        }
    }
    
    private function applyCouponDiscount(PriceBreakdown &$breakdown, string $couponCode, Carbon $date): void
    {
       $coupon = Coupon::where('code', $couponCode)->where('active', true)->first();
        if (!$coupon) return; // Or throw an exception with a message

        // TODO: Add full coupon validation logic (usage limits, etc.)
        // For now, we'll just check the conditions JSON.
        
        $couponConditions = $coupon->conditions ?? [];
        // Check applicability and date conditions from the coupon's JSON
        // ... (Logic to check min_amount, days_of_week, applicable_ticket_tiers)

        // If valid, calculate the effect
        $discountAmount = 0.0;
        if ($coupon->discount_type === 'buy_x_get_y_free') {
            $discountAmount = $this->calculateBogoDiscount($breakdown->lineItems, $coupon->effects);
        } // ... add logic for percentage and fixed from coupons
        
        if ($discountAmount > 0) {
             $breakdown->appliedDiscounts->push([
                'name' => "Coupon: {$coupon->code}",
                'amount' => round($discountAmount, 2),
            ]);
        }
    }

    private function processAddOnCosts(PriceBreakdown &$breakdown, BookableService $service, array $selectedAddOns): void
    {
        $addOnIds = array_column($selectedAddOns, 'add_on_id');
        if (empty($addOnIds)) return;

        $addOns = $service->addons()->whereIn('id', $addOnIds)->get()->keyBy('id');
        $total = 0;

        foreach ($selectedAddOns as $selection) {
            $addOn = $addOns->get($selection['add_on_id']);
            // Only add cost if the add-on is NOT included in the ticket
            if ($addOn && !$addOn->is_included_in_ticket) {
                $total += $addOn->price * $selection['quantity'];
            }
        }
        $breakdown->addOnsTotal = $total;
    }
    
    // ... all other private helper methods (areConditionsMet, calculateBogoDiscount, etc.)
    // --- The Rule/Condition/Modification Helper methods from before ---
    // (areConditionsMet, calculateDiscountAmount, checkDateCondition, etc.)
    // Note: calculateBogoDiscount now takes $effects instead of $mod.
    private function calculateBogoDiscount(Collection $tickets, array $effects): float
    {
        $targetTier = $tickets->firstWhere('ticket_tier_id', $effects['ticket_tier_id']);
        if (!$targetTier) return 0.0;

        $timesEarned = floor($targetTier['quantity'] / $effects['buy_quantity']);
        $freeTickets = $timesEarned * $effects['get_quantity'];
        $freeTickets = min($targetTier['quantity'], $freeTickets);

        return $freeTickets * $targetTier['unit_price'];
    }

    /**
     * The "Brain" - Determines if a rule's conditions are met.
     * It checks all conditions within a rule's 'conditions' object.
     */
    private function areConditionsMet(PricingRule $rule, Collection $lineItems, Carbon $date): bool
    {
        // No conditions means the rule is always applicable.
        if (empty($rule->conditions)) {
            return true;
        }

        $conditions = $rule->conditions;
        
        // This design allows for multiple condition types to be combined in the future,
        // though for now we assume only one type per rule.
        // A rule is only valid if ALL its defined conditions are met.

        if (isset($conditions['type']) && $conditions['type'] === 'date') {
            if (!$this->checkDateCondition($conditions, $date)) {
                return false; // If date condition fails, the whole rule fails.
            }
        }

        if (isset($conditions['type']) && $conditions['type'] === 'ticket_quantity') {
            if (!$this->checkTicketQuantityCondition($conditions, $lineItems)) {
                return false; // If quantity condition fails, the whole rule fails.
            }
        }
        
        // If we've passed all the checks, the conditions are met.
        return true;
    }
    
    // --- Specific Condition Checkers ---

    private function checkDateCondition(array $conditions, Carbon $date): bool
    {
        // Check 1: Date Range
        if (isset($conditions['from_date']) && isset($conditions['to_date'])) {
            if (!$date->between(Carbon::parse($conditions['from_date']), Carbon::parse($conditions['to_date']))) {
                return false;
            }
        }

        // Check 2: Days of the Week
        if (isset($conditions['days_of_week']) && !empty($conditions['days_of_week'])) {
            if (!in_array($date->dayOfWeek, $conditions['days_of_week'])) {
                return false;
            }
        }

        // Check 3: Specific Dates
        if (isset($conditions['specific_dates']) && !empty($conditions['specific_dates'])) {
            if (!in_array($date->toDateString(), $conditions['specific_dates'])) {
                return false;
            }
        }
        
        // If no date conditions were specified or if all specified conditions passed.
        return true;
    }

    private function checkTicketQuantityCondition(array $conditions, Collection $lineItems): bool
    {
        $targetTier = $lineItems->firstWhere('ticket_tier_id', $conditions['ticket_tier_id']);
        if (!$targetTier) {
            // The user hasn't even selected this ticket type, so the condition isn't met.
            return false; 
        }

        return $targetTier['quantity'] >= $conditions['min_quantity'];
    }

}