<?php

namespace App\Models;

use App\Models\BookableService;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class ServiceAppointment extends Model
{
    use SoftDeletes;
    protected $fillable = ['tenant_id','duration_minutes', 'buffer_time_minutes', 'requires_provider'];
    public function bookableService(): MorphOne { 
        return $this->morphOne(BookableService::class, 'serviceable'); 
    }
}
