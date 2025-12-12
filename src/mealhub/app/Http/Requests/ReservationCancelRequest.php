<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class ReservationCancelRequest extends FormRequest
{
    public function authorize(): bool { return true; }

    public function rules(): array
    {
        return [
            // 僅支援取消單筆
            'reservationId' => ['required','integer','min:1'],
        ];
    }
}
