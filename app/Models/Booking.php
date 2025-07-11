<?php

namespace App\Models;

use App\Models\AddOn;
use App\Models\Tenant;
use App\Models\Customers;
use App\Models\BookableService;
use Illuminate\Database\Eloquent\Model;

class Booking extends Model
{
    protected $fillable = ['booking_reference', 'tenant_id', 'bookable_service_id', 'customer_id', 'booking_data_snapshot', 'total_amount', 'status'];

    public function tenant(): BelongsTo { 
        return $this->belongsTo(Tenant::class); 
    }

    public function customer(): BelongsTo { 
        return $this->belongsTo(Customers::class); 
    }

    public function service(): BelongsTo { 
        return $this->belongsTo(BookableService::class); 
    }

    public function addons() { 
        return $this->belongsToMany(AddOn::class, 'booking_addon')->withPivot('quantity', 'price_at_booking'); 
    }
}
