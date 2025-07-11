<?php

namespace App\Models;

use App\Models\Tenant;
use App\Models\BookableService;
use Illuminate\Database\Eloquent\Model;

class AddOn extends Model
{
    protected $fillable = [
        'tenant_id',
        'bookable_service_id',
        'name',
        'base_price',
        'order_column',
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
