<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class CouponResource extends JsonResource
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
            'code' => $this->code,
            'active' => $this->active,
            'discount_type' => $this->discount_type,
            'discount_value' => $this->discount_value,
            'max_uses' => $this->max_uses,
            'used_count' => $this->used_count,

            // Return the full, structured objects
            'conditions' => $this->conditions,
            'effects' => $this->effects,
        ];

    }
}
