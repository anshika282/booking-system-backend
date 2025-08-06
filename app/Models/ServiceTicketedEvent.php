<?php

namespace App\Models;

use App\Models\Location;
use App\Models\BookableService;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ServiceTicketedEvent extends Model
{
    use SoftDeletes;
    protected $fillable = ['tenant_id','venue_name', 'address', 'requires_waiver', 'seating_map_config','location_id'];

    protected function casts(): array { 
        return ['seating_map_config' => 'array','requires_waiver' => 'boolean']; 
    }

    public function bookableService(): MorphOne {
         return $this->morphOne(BookableService::class, 'serviceable'); 
    }
    public function location(): BelongsTo
    {
        return $this->belongsTo(Location::class);
    }
}
