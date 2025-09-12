<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class RestaurantUpdateRequest extends FormRequest
{
    public function authorize(): bool { return true; }

    public function rules(): array
    {
        return [
            'name'          => ['sometimes','string','max:255'],
            'description'   => ['sometimes','nullable','string'],
            'address'       => ['sometimes','nullable','string','max:255'],
            'note'          => ['sometimes','nullable','string'],
            'tableBuckets'  => ['sometimes','array'],
            // timeslots（start/end 陣列），若提供則驗證每個元素
            'timeslots'           => ['sometimes','array','min:1'],
            'timeslots.*.start'   => ['required_with:timeslots','regex:/^\d{2}:\d{2}$/'],
            'timeslots.*.end'     => ['required_with:timeslots','regex:/^\d{2}:\d{2}$/'],
        ];
    }
}

