<?php

namespace App\Models;

use App\Models\Tenant;
use App\Models\BookableService;
use Illuminate\Database\Eloquent\Model;

class TicketTier extends Model
{
    protected $fillable = ['tenant_id','bookable_service_id', 'name', 'base_price', 'min_quantity', 'max_quantity', 'order_column'];

    public function bookableService(): BelongsTo
    {
        return $this->belongsTo(BookableService::class);
    }

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }
}
