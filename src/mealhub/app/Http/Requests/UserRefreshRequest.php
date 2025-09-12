<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class UserRefreshRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'refreshToken' => ['required', 'string'],
        ];
    }
}
