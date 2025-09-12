<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use App\Enums\ReservationStatus;

class Reservation extends Model
{
    protected $table = 'reservations';

    protected $fillable = [
        'restaurant_id','user_id','reserve_date','timeslot','party_size','status','code','short_token'
    ];

    protected $casts = [
        'status' => ReservationStatus::class,
    ];
}
