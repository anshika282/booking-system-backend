<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Location extends Model
{
     use HasFactory, SoftDeletes;

    protected $fillable = [
        'tenant_id',
        'name',
        'address',
        'city',
        'state',
        'postal_code',
        'country',
    ];

    /**
     * A Location belongs to one Tenant.
     */
    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }

    /**
     * A Location can be associated with many ServiceTicketedEvents.
     */
    public function serviceTicketedEvents(): HasMany
    {
        return $this->hasMany(ServiceTicketedEvent::class);
    }
}
