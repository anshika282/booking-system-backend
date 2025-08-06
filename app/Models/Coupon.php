<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Coupon extends Model
{
    use HasFactory;

    protected $fillable = [
        'tenant_id',
        'bookable_service_id',
        'code',
        'discount_type',
        'discount_value',
        'max_uses',
        'used_count',
         'conditions',
        'effects',   
        'active',
    ];

    protected $casts = [
        'discount_value' => 'float',
        'conditions' => 'array',
        'effects' => 'array',
        'active' => 'boolean',
    ];

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }

    /**
     * A coupon belongs to a single service.
     */
    public function bookableService(): BelongsTo
    {
        return $this->belongsTo(BookableService::class);
    }
}