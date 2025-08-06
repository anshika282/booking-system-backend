<?php

namespace App\Models;

use App\Models\Tenant;
use App\Models\BookableService;
use Illuminate\Database\Eloquent\Model;

class AddOn extends Model
{
    /**
     * The table associated with the model.
     *
     * This explicitly tells Eloquent to use the 'addons' table
     * instead of trying to guess 'add_ons'.
     *
     * @var string
     */
    protected $table = 'addons';
    
    protected $fillable = [
        'tenant_id',
        'bookable_service_id',
        'name',
        'type',
        'is_included_in_ticket',
        'price',
        'order_column',
    ];

    protected $casts = [
        'price' => 'float',
         'is_included_in_ticket' => 'boolean'
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
