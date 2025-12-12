<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class UserUpdateSelfRequest extends FormRequest
{
    public function authorize(): bool { return true; }

    public function rules(): array
    {
        return [
            'firstName' => ['sometimes','string','max:255'],
            'lastName'  => ['sometimes','string','max:255'],
            'phone'     => ['sometimes','string','max:50'],
            'password'  => ['sometimes','string','min:8'],
        ];
    }
}

