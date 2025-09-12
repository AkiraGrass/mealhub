<?php

namespace App\Http\Resources;

use Illuminate\Http\Resources\Json\JsonResource;

class RestaurantResource extends JsonResource
{
    public function toArray($request): array
    {
        $restaurant = $this->resource;
        return [
            'id'           => $restaurant->id,
            'name'         => $restaurant->name,
            'description'  => $restaurant->description,
            'address'      => $restaurant->address,
            'note'         => $restaurant->note,
            'timeslots'    => $restaurant->timeslots,
            'tableBuckets' => $restaurant->table_buckets,
            'status'       => $restaurant->status,
        ];
    }
}
