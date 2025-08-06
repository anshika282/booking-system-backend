<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class PricingRuleResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        // The 'conditions' and 'price_modification' are stored as JSON in the DB.
        // The model's $casts property automatically decodes them into PHP arrays.
        $conditions = $this->conditions ?? [];
        $priceModification = $this->price_modification ?? [];

        return [
            'id' => $this->id,
            'bookable_service_id' => $this->bookable_service_id,
            'name' => $this->name,
            'type' => $this->type, // Ensure the top-level type is included
            'active' => $this->active,
            'priority' => $this->priority,
            'category' => $this->category,
            'is_stackable' => $this->is_stackable,
            
            // --- EXPLICITLY BUILD THE NESTED OBJECTS ---
            // This ensures all keys are always present, even if their value is null.
            
            'conditions' => [
                'type' => $conditions['type'] ?? null,
                
                // Date condition fields
                'from_date' => $conditions['from_date'] ?? null,
                'to_date' => $conditions['to_date'] ?? null,
                'days_of_week' => $conditions['days_of_week'] ?? null,
                'specific_dates' => $conditions['specific_dates'] ?? null,

                // Ticket quantity condition fields
                'ticket_tier_id' => $conditions['ticket_tier_id'] ?? null,
                'min_quantity' => $conditions['min_quantity'] ?? null,
            ],
            
            'price_modification' => [
                'type' => $priceModification['type'] ?? null,
                
                // Fields for 'set_fixed_price'
                'tiers' => $priceModification['tiers'] ?? null,

                // Fields for 'total_amount_discount'
                'calculation_mode' => $priceModification['calculation_mode'] ?? null,
                'amount' => $priceModification['amount'] ?? null, // Using 'amount' as per your last request

                // Fields for 'buy_x_get_y_free'
                'ticket_tier_id' => $priceModification['ticket_tier_id'] ?? null,
                'buy_quantity' => $priceModification['buy_quantity'] ?? null,
                'get_quantity' => $priceModification['get_quantity'] ?? null,
            ],
            
            'created_at' => $this->created_at->toIso8601String(),
            'updated_at' => $this->updated_at->toIso8601String(),
        ];
    }
}