<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class RestaurantReservationsRequest extends FormRequest
{
    public function authorize(): bool { return true; }

    public function rules(): array
    {
        return [
            'date'     => ['required','date_format:Y-m-d'],
            'timeslot' => ['nullable','string'], // 例如 "18:00-19:30"
        ];
    }
}

