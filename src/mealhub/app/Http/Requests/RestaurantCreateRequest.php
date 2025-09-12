<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class RestaurantCreateRequest extends FormRequest
{
    public function authorize(): bool { return true; }

    public function rules(): array
    {
        return [
            'name' => ['required','string','max:255'],
            'description' => ['nullable','string'],
            'address' => ['nullable','string','max:255'],
            'note' => ['nullable','string'],
            'tableBuckets' => ['nullable','array'], // e.g. {"2":10,"4":5}
            'timeslots' => ['nullable','array'],
            'timeslots.*.start' => ['required_with:timeslots','regex:/^\d{2}:\d{2}$/'],
            'timeslots.*.end'   => ['required_with:timeslots','regex:/^\d{2}:\d{2}$/'],
        ];
    }
}
