<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class UserLoginRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'email'      => 'nullable|email|required_without:phone',
            'phone'      => 'nullable|string|max:50|required_without:email',
            'password' => 'required|string|min:6',
            'deviceType' => 'required|string|in:WEB,ANDROID,IOS|max:10',
        ];
    }
}
