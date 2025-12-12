<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class ReservationUpdateRequest extends FormRequest
{
    public function authorize(): bool { return true; }

    public function rules(): array
    {
        return [
            'reservationId' => ['required','integer','min:1'],
            'start'         => ['required','regex:/^\d{2}:\d{2}$/'],
            'end'           => ['required','regex:/^\d{2}:\d{2}$/'],
        ];
    }
}

