<?php

namespace App\Models;

use App\Models\Tenant;
use App\Models\BookableService;
use Illuminate\Database\Eloquent\Model;

class PricingRule extends Model
{
    protected $fillable = [
        'tenant_id', 
        'bookable_service_id', 
        'name',
        'type', 
        'conditions', 
        'price_modification', 
        'priority', 
        'active'
    ];

    public function bookableService(): BelongsTo
    {
        return $this->belongsTo(BookableService::class);
    }

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }  
}
