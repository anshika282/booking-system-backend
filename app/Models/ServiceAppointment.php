<?php

namespace App\Models;

use App\Models\BookableService;
use Illuminate\Database\Eloquent\Model;

class ServiceAppointment extends Model
{
    protected $fillable = ['tenant_id','duration_minutes', 'buffer_time_minutes', 'requires_provider'];

    public function bookableService(): MorphOne { 
        return $this->morphOne(BookableService::class, 'serviceable'); 
    }
}
