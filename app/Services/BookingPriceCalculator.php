<?php

namespace App\Services;

use Carbon\Carbon;
use App\Models\Coupon;
use App\Models\PricingRule; 
use App\Models\BookableService;
use Illuminate\Support\Collection;
use App\DataTransferObjects\PriceBreakdown;

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
    public function calculate(BookableService $service, string $date, array $selections): PriceBreakdown // <-- Return type is the DTO object
    {
        $bookingDate = Carbon::parse($date);
        $activeRules = $service->pricingRules()->where('active', true)->orderBy('priority', 'asc')->get();

        // 1. Create a new instance of our DTO. This will be our state container.
        $breakdown = new PriceBreakdown();

        // 2. Pass the DTO object BY REFERENCE to each phase of the calculation.
        //    Each method will modify the object directly.

        // Phase 1 & 2: Establish Line Items and Subtotal
        $this->processLineItems($breakdown, $service, $activeRules, $bookingDate, $selections['tickets'] ?? []);

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

        // 3. Return the single, populated DTO object.
        return $breakdown;
    }

    /**
     * Fetches and prepares the ticket data with quantities.
     */
    private function getTicketDetails(BookableService $service, array $selectedTiers): Collection
    {
        $tierIds = array_column($selectedTiers, 'tier_id');
        $baseTiers = $service->ticketTiers()->whereIn('id', $tierIds)->get();

        return $baseTiers->map(function ($tier) use ($selectedTiers) {
            $quantity = collect($selectedTiers)->firstWhere('tier_id', $tier->id)['quantity'];
            return [
                'id' => $tier->id,
                'name' => $tier->name,
                'base_price' => (float) $tier->base_price,
                'price' => (float) $tier->base_price, // Start with price = base_price
                'quantity' => (int) $quantity,
            ];
        });
    }

    /**
     * Fetches all active rules for the service, ordered by priority.
     */
    private function getActiveRulesForService(BookableService $service): Collection
    {
        return $service->pricingRules()
            ->where('active', true)
            ->orderBy('priority', 'asc')
            ->get();
    }
    
    /**
     * PHASE 1 LOGIC: Modifies the base prices of tickets.
     */
    private function applyBasePriceAdjustments(Collection $tickets, Collection $rules, Carbon $date): Collection
    {
        $adjustmentRules = $rules->where('category', 'base_price_adjustment');

        // "First match wins" for this category.
        foreach ($adjustmentRules as $rule) {
            if ($this->areConditionsMet($rule, $tickets, $date)) {
                $tickets = $this->applyModification($tickets, $rule);
                break; // Apply only the highest-priority matching rule
            }
        }
        return $tickets;
    }

    /**
     * PHASE 2 LOGIC: Calculates all applicable discounts.
     */
    private function applyDiscounts(float $subtotal, Collection $tickets, Collection $rules, Carbon $date): array
    {
        $discountRules = $rules->where('category', 'discount');
        $appliedDiscounts = [];
        $nonStackableRuleApplied = false;

        foreach ($discountRules as $rule) {
            if ($nonStackableRuleApplied) break;

            if ($this->areConditionsMet($rule, $tickets, $date)) {
                // 'applyModification' for discounts should return a discount object, not change ticket prices.
                $discountAmount = $this->calculateDiscountAmount($subtotal, $tickets, $rule);
                
                if ($discountAmount > 0) {
                    $appliedDiscounts[] = [
                        'name' => $rule->name,
                        'amount' => $discountAmount,
                    ];

                    if (!$rule->is_stackable) {
                        $nonStackableRuleApplied = true;
                    }
                }
            }
        }
        return $appliedDiscounts;
    }

    /**
     * The "Brain" - Determines if a rule's conditions are met.
     */
    private function areConditionsMet(PricingRule $rule, Collection $tickets, Carbon $date): bool
    {
        if (empty($rule->conditions)) {
            return true; // No conditions means it always applies.
        }

        $conditions = $rule->conditions;

        return match ($conditions['type']) {
            'date' => $this->checkDateCondition($conditions, $date),
            'ticket_quantity' => $this->checkTicketQuantityCondition($conditions, $tickets),
            default => false,
        };
    }

    /**
     * Applies a modification to a collection of tickets (used for base price changes).
     */
    private function applyModification(Collection $tickets, PricingRule $rule): Collection
    {
        $mod = $rule->price_modification;

        if ($mod['type'] === 'set_fixed_price') {
            $targetTierIds = array_column($mod['tiers'], 'ticket_tier_id');
            return $tickets->map(function ($ticket) use ($mod, $targetTierIds) {
                if (in_array($ticket['id'], $targetTierIds)) {
                    $modTier = collect($mod['tiers'])->firstWhere('ticket_tier_id', $ticket['id']);
                    $ticket['price'] = (float) $modTier['value'];
                }
                return $ticket;
            });
        }
        
        // ... logic for 'fixed_amount_change', etc. would go here ...

        return $tickets;
    }
    
    /**
     * Calculates the value of a discount modification.
     */
    private function calculateDiscountAmount(float $subtotal, Collection $tickets, PricingRule $rule): float
    {
        $mod = $rule->price_modification;
        
        return match ($mod['type']) {
            'total_amount_discount' => $this->calculateTotalAmountDiscount($subtotal, $mod),
            'buy_x_get_y_free' => $this->calculateBogoDiscount($tickets, $mod),
            default => 0.0,
        };
    }

    // --- Specific Condition Checkers ---

     private function checkDateCondition(array $conditions, Carbon $date): bool
    {
        if (isset($conditions['from_date']) && isset($conditions['to_date'])) {
            if (!$date->between(Carbon::parse($conditions['from_date']), Carbon::parse($conditions['to_date']))) {
                return false;
            }
        }

        if (isset($conditions['days_of_week']) && !empty($conditions['days_of_week'])) {
            if (!in_array($date->dayOfWeek, $conditions['days_of_week'])) {
                return false;
            }
        }

        if (isset($conditions['specific_dates']) && !empty($conditions['specific_dates'])) {
            if (!in_array($date->toDateString(), $conditions['specific_dates'])) {
                return false;
            }
        }
        
        return true;
    }

    private function checkTicketQuantityCondition(array $conditions, Collection $tickets): bool
    {
        $targetTier = $tickets->firstWhere('id', $conditions['ticket_tier_id']);
        if (!$targetTier) return false;

        return $targetTier['quantity'] >= $conditions['min_quantity'];
    }

    // --- Specific Discount Calculators ---

    private function calculateTotalAmountDiscount(float $subtotal, array $mod): float
    {
        if ($mod['calculation_mode'] === 'percentage') {
            // Use 'amount' key as per our final validation design
            return $subtotal * (($mod['amount'] ?? 0) / 100); 
        }
        if ($mod['calculation_mode'] === 'fixed') {
            return (float) ($mod['amount'] ?? 0);
        }
        return 0.0;
    }

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
     * Populates the line items and subtotals on the PriceBreakdown DTO.
     */
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
                break;
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
        if (!$coupon) return;
        
        // This method would contain the full validation and calculation logic for coupons
        $discountAmount = 0.0;
        if ($coupon->discount_type === 'buy_x_get_y_free') {
            $discountAmount = $this->calculateBogoDiscount($breakdown->lineItems, $coupon->effects);
        }
        
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
            if ($addOn && !$addOn->is_included_in_ticket) {
                $total += $addOn->price * $selection['quantity'];
            }
        }
        $breakdown->addOnsTotal = $total;
    }

    private function areConditionsMet1(PricingRule $rule, Collection $lineItems, Carbon $date): bool
    {
        if (empty($rule->conditions)) {
            return true;
        }

        $conditions = $rule->conditions;
        
        if (isset($conditions['type']) && $conditions['type'] === 'date') {
            if (!$this->checkDateCondition($conditions, $date)) {
                return false;
            }
        }

        if (isset($conditions['type']) && $conditions['type'] === 'ticket_quantity') {
            if (!$this->checkTicketQuantityCondition($conditions, $lineItems)) {
                return false;
            }
        }
        
        return true;
    }

    /**
     * Calculates the dynamically adjusted prices for ALL available ticket tiers for a given date.
     * This is used to populate the booking form before the user has selected quantities.
     *
     * @param BookableService $service
     * @param string $date
     * @return Collection
     */
    public function getPricedTicketTiersForDate(BookableService $service, string $date): Collection
    {
        $bookingDate = Carbon::parse($date);
        $activeRules = $service->pricingRules()
                               ->where('active', true)
                               ->where('category', 'base_price_adjustment')
                               ->orderBy('priority', 'asc')
                               ->get();

        $allTiers = $service->ticketTiers;

        // Find the single highest-priority adjustment rule that applies
        $appliedAdjustmentRule = null;
        foreach ($activeRules as $rule) {
            // For this initial check, conditions can't depend on ticket quantity, only date.
            if ($this->checkDateCondition($rule->conditions ?? [], $bookingDate)) {
                $appliedAdjustmentRule = $rule;
                break;
            }
        }

        return $allTiers->map(function ($tier) use ($appliedAdjustmentRule) {
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

            return [
                'id' => $tier->id,
                'name' => $tier->name,
                'price' => round($finalPrice, 2), // The final, adjusted price to display
                'base_price' => $basePrice,
                'min_quantity' => $tier->min_quantity,
                'max_quantity' => $tier->max_quantity,
            ];
        });
    }
    
}