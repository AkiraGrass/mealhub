<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class RestaurantReservationSlot extends Model
{
    protected $table = 'restaurant_reservation_slots';

    protected $fillable = [
        'restaurant_id','reserve_date','timeslot','party_size','reserved',
    ];
}

