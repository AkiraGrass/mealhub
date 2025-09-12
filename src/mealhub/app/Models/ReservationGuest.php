<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ReservationGuest extends Model
{
    protected $table = 'reservation_guests';

    protected $fillable = ['reservation_id','email'];
}

