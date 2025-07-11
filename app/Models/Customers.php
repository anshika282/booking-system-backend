<?php

namespace App\Models;

use App\Models\Booking;
use App\Models\BookingIntent;
use Illuminate\Database\Eloquent\Model;

class Customers extends Model
{
     protected $fillable = [
        'name',
        'email',
        'phone_number',
        'billing_address_line_1',
        'billing_city',
        'billing_postal_code',
        'billing_country',
    ];

    public function bookings(): HasMany
    {
        return $this->hasMany(Booking::class);
    }

    public function bookingIntents(): HasMany { 
        return $this->hasMany(BookingIntent::class); 
    }

}
