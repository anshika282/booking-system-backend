<?php

namespace App\Models;

use App\Models\Tenant;
use App\Models\BookableService;
use App\Services\TenantManager;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;

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

    /**
     * Customize route model binding for TicketTier.
     */
    public function resolveRouteBinding($value, $field = null)
    {
        return $this->resolveRouteBinding(
            $value,
            $field,
            'service',
            'BookableService',
            'bookable_service_id',
            'ticket_tier'
        );
    }
}
