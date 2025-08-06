<?php

namespace App\Models;

use App\Models\Tenant;
use App\Models\BookableService;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PricingRule extends Model
{
    protected $fillable = [
        'tenant_id', 
        'bookable_service_id', 
        'name',
        'type', 
        'conditions', 
        'price_modification', 
        'category',      
        'is_stackable',
        'priority', 
        'active'
    ];
/**
     * The attributes that should be cast to native types.
     *
     * @var array<string, string>
     */
    protected $casts = [
        'conditions' => 'array',
        'price_modification' => 'array',
        'active' => 'boolean',
        'is_stackable' => 'boolean', // <-- ADD THIS
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
