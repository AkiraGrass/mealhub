<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use App\Enums\RestaurantStatus;

class Restaurant extends Model
{
    protected $table = 'restaurants';

    protected $fillable = ['name','description','address','note','timeslots','table_buckets','status'];

    protected $casts = [
        'table_buckets' => 'array',
        'timeslots'     => 'array',
        'status'        => RestaurantStatus::class,
    ];
}
