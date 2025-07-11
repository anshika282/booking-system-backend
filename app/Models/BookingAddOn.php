<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class BookingAddOn extends Model
{
    protected $table = 'booking_addon';
    public $timestamps = false;
    protected $fillable = ['booking_id', 'addon_id', 'quantity', 'price_at_booking'];
}
