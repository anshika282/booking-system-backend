<?php

namespace App\Models;

use App\Models\AddOn;
use App\Models\Tenant;
use App\Models\Customers;
use App\Models\BookableService;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class Booking extends Model
{
    protected $fillable = ['booking_reference', 'tenant_id', 'bookable_service_id', 'customer_id', 'booking_data_snapshot', 'total_amount', 'status'];

    protected $casts = [
        'booking_data_snapshot' => 'array',
        'total_amount' => 'float',
    ];

    public function tenant(): BelongsTo { 
        return $this->belongsTo(Tenant::class); 
    }

    public function customer(): BelongsTo { 
        return $this->belongsTo(Customers::class); 
    }

    public function service(): BelongsTo { 
        return $this->belongsTo(BookableService::class); 
    }

    /**
     * The relationship to the AddOn model through the 'booking_addon' pivot table.
     *
     * @return \Illuminate\Database\Eloquent\Relations\BelongsToMany
     */
    public function addons(): BelongsToMany
    {
        // We explicitly tell Laravel the names of the foreign keys in the pivot table.
        // The second argument is the pivot table name.
        // The third argument is the "parent" foreign key (for Booking).
        // The fourth argument is the "related" foreign key (for AddOn).
        return $this->belongsToMany(
            AddOn::class,       // Related model
            'booking_addon',    // Pivot table name
            'booking_id',       // Foreign key for THIS model (Booking)
            'addon_id'         // Foreign key for the related model (AddOn)
        )->withPivot('quantity', 'price_at_booking');
    }
}
