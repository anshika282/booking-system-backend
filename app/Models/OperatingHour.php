<?php

namespace App\Models;

use App\Models\Tenant;
use App\Models\BookableService;
use Illuminate\Database\Eloquent\Model;

class OperatingHour extends Model
{
    protected $fillable = ['tenant_id','bookable_service_id', 'day_of_week', 'open_time', 'close_time'];

    public function bookableService(): BelongsTo
    {
        return $this->belongsTo(BookableService::class);
    }

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    } 
}
