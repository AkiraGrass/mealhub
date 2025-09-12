<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class RestaurantUpdateTimeslotsRequest extends FormRequest
{
    public function authorize(): bool { return true; }

    public function rules(): array
    {
        return [
            'timeslots' => ['required','array','min:1'],
            'timeslots.*.start' => ['required','regex:/^\d{2}:\d{2}$/'],
            'timeslots.*.end'   => ['required','regex:/^\d{2}:\d{2}$/'],
        ];
    }
}

