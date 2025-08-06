<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class BookingResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $service = $this->whenLoaded('service');
        $customer = $this->whenLoaded('customer');

        return [
            'id' => $this->id,
            'booking_reference' => $this->booking_reference,
            'status' => $this->status,
            'total_amount' => $this->total_amount,
            'booked_at' => $this->created_at->toIso8601String(),
            
            // Include details about the service that was booked.
            'service' => [
                'id' => $service->id ?? null,
                'name' => $service->name ?? null,
            ],

            // Include details about the customer who booked.
            'customer' => [
                'id' => $customer->id ?? null,
                'name' => $customer->name ?? null,
                'email' => $customer->email ?? null,
            ],
            
            // The full, auditable snapshot of what was purchased.
            'booking_details_snapshot' => $this->booking_data_snapshot,
        ];
    }
}
