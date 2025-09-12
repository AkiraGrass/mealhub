<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class ReservationCreateRequest extends FormRequest
{
    public function authorize(): bool { return true; }

    public function rules(): array
    {
        return [
            'restaurantId' => ['required','integer','min:1'],
            'date'         => ['required','date_format:Y-m-d'],
            'start'        => ['required','regex:/^\d{2}:\d{2}$/'],
            'end'          => ['required','regex:/^\d{2}:\d{2}$/'],
            'partySize'    => ['required','integer','min:1'],
            'guestEmails'  => ['nullable','array'],
            'guestEmails.*'=> ['email'],
        ];
    }
}
