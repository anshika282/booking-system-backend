<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class BookableServicesResource extends JsonResource
{
     /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'tenant_id' => $this->tenant_id,
            'name' => $this->name,
            'duration_minutes' => $this->duration_minutes,
            'capacity_consumption_mode' => $this->slot_consumption_model,
            'slot_selection_mode' => $this->slot_selection_mode,
            'status' => $this->status,
            'booking_window' => [
                'min_days_advance' => $this->booking_window_min_days,
                'max_days_advance' => $this->booking_window_max_days,
            ],
            'default_capacity' => $this->default_capacity,
            // Conditionally merge the specific service type details
            // This uses the power of polymorphism to return the correct details.
            'details' => $this->whenLoaded('serviceable'),
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
        ];
    }
}
