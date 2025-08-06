<?php

namespace App\Models;

use App\Models\Tenant;
use App\Models\BookableService;
use Illuminate\Database\Eloquent\Model;

class AvailabilitySlot extends Model
{
    protected $fillable = ['tenant_id', 'bookable_service_id', 'start_time', 'end_time', 'capacity', 'booked_count', 'status'];

    public function bookableService(): BelongsTo
    {
        return $this->belongsTo(BookableService::class);
    }

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    } 
}
