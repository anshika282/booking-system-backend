<?php

namespace App\Models;

use App\Models\BookableService;
use Illuminate\Database\Eloquent\Model;

class ServiceTicketedEvent extends Model
{
    protected $fillable = ['tenant_id','venue_name', 'address', 'requires_waiver', 'seating_map_config'];

    protected function casts(): array { 
        return ['seating_map_config' => 'array']; 
    }

    public function bookableService(): MorphOne {
         return $this->morphOne(BookableService::class, 'serviceable'); 
    }
}
