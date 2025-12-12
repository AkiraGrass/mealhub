<?php

namespace App\Http\Resources;

use Illuminate\Http\Resources\Json\JsonResource;

class RestaurantAdminResource extends JsonResource
{
    public function toArray($request): array
    {
        $user = $this->resource;
        return [
            'id'        => $user->id,
            'firstName' => $user->first_name,
            'lastName'  => $user->last_name,
            'email'     => $user->email,
        ];
    }
}
