<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class RestaurantAvailabilityRequest extends FormRequest
{
    public function authorize(): bool { return true; }

    public function rules(): array
    {
        return [
            'date'      => ['required','date_format:Y-m-d'],
            'partySize' => ['required','integer','min:1'],
        ];
    }
}

