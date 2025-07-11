<?php

namespace App\Models;

use App\Models\Customers;
use App\Models\BookableService;
use Illuminate\Database\Eloquent\Model;

class BookingIntent extends Model
{
    protected $fillable = ['session_id', 'tenant_id', 'bookable_service_id', 'customer_id', 'intent_data', 'subtotal_amount', 'discounts_amount', 'surcharges_amount', 'addons_amount', 'total_amount', 'last_step_completed', 'status', 'expires_at'];

    public function tenant(): BelongsTo { 
        return $this->belongsTo(Tenant::class); 
    }
    public function customer(): BelongsTo { 
        return $this->belongsTo(Customers::class); 
    }
    public function bookableService(): BelongsTo { 
        return $this->belongsTo(BookableService::class, 'bookable_service_id'); 
    }
}
