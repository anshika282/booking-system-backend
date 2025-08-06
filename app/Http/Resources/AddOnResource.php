<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class AddOnResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
           'id' => $this->id,
            'name' => $this->name,
            'is_included_in_ticket' => $this->is_included_in_ticket, // <-- ADDED
            'base_price' => $this->price,
            'type' => $this->type,
            'bookable_service_id' => $this->bookable_service_id,
        ];
    }
}