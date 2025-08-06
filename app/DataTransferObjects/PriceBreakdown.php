<?php

namespace App\DataTransferObjects;

use Illuminate\Support\Collection;

/**
 * A simple, typed object to hold the state of a price calculation.
 * This makes the flow of data through the pricing engine explicit and predictable.
 */
class PriceBreakdown
{
    public float $baseSubtotal = 0.0;
    public float $adjustedSubtotal = 0.0;
    public Collection $lineItems;
    public Collection $appliedDiscounts;
    public float $addOnsTotal = 0.0;
    public float $finalTotal = 0.0;

    public function __construct()
    {
        // Initialize collections to avoid errors on empty carts.
        $this->lineItems = new Collection();
        $this->appliedDiscounts = new Collection();
    }

    /**
     * Calculates the final total based on the current state of the breakdown.
     */
    public function calculateFinalTotal(): void
    {
        $totalDiscountAmount = $this->appliedDiscounts->sum('amount');
        $this->finalTotal = max(0, $this->adjustedSubtotal - $totalDiscountAmount + $this->addOnsTotal);
    }
    
    /**
     * A helper method to convert the DTO to a plain array for API responses.
     */
    public function toArray(): array
    {
        return [
            'base_subtotal' => round($this->baseSubtotal, 2),
            'adjusted_subtotal' => round($this->adjustedSubtotal, 2),
            'line_items' => $this->lineItems->toArray(),
            'discounts' => $this->appliedDiscounts->toArray(),
            'add_ons_total' => round($this->addOnsTotal, 2),
            'final_total' => round($this->finalTotal, 2),
        ];
    }
}