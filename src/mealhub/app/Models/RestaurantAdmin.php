<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class RestaurantAdmin extends Model
{
    protected $table = 'restaurant_admins';

    protected $fillable = ['restaurant_id','user_id','role'];
}

