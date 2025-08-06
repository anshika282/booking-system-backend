<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class TicketTierResource extends JsonResource
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
            'name' => $this->name,
            'base_price' => $this->base_price,
            'min_quantity' => $this->min_quantity,
            'max_quantity' => $this->max_quantity,
            'order_column' => $this->order_column,
            'bookable_service_id' => $this->bookable_service_id,
        ];
    }
}
