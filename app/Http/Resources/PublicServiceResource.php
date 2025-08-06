<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use App\Http\Resources\CouponResource;
use App\Http\Resources\TicketTierResource;
use App\Http\Resources\PricingRuleResource;
use App\Http\Resources\OperatingHourResource;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin \App\Models\BookableService
 */
class PublicServiceResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * This resource is for public consumption. It should only include data
     * necessary for a customer to view and interact with the booking form.
     * It deliberately omits internal data like status, tenant_id, etc.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'description' => $this->description,

            // Include details from the polymorphic relationship (e.g., venue_name)
            'details' => $this->whenLoaded('serviceable', function () {
                // We can be selective about which details to show.
                // For a ticketed event, the venue name is important.
                if ($this->serviceable_type === 'App\\Models\\ServiceTicketedEvent') {
                    return [
                        'venue_name' => $this->serviceable->venue_name,
                        'address' => $this->serviceable->address,
                    ];
                }
                // For an appointment, there might not be any public details to add.
                return null;
            }),

            // Include the list of all available ticket tiers for this service.
            // We use another resource to ensure its structure is also controlled.
            'ticket_tiers' => TicketTierResource::collection($this->whenLoaded('ticketTiers')),
            
            // Include the list of all available add-ons for this service.
            'add_ons' => AddOnResource::collection($this->whenLoaded('addons')),
            // Include any applicable coupons for this service.
            'coupons' => CouponResource::collection($this->whenLoaded('coupons')),
            // Include the availability slots for this service.
            //'availability_slots' => AvailabilitySlotResource::collection($this->whenLoaded('availability_slots')),       
            // Include the operating hours for this service.
            'operating_hours' => OperatingHourResource::collection($this->whenLoaded('operatingHours')),
            // Include any pricing rules that apply to this service.
            'pricing_rules' => PricingRuleResource::collection($this->whenLoaded('pricingRules')),    
        ];
    }
}