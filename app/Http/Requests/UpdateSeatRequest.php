<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class UpdateSeatRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'label' => 'sometimes|required|string|max:255',
            'price' => 'sometimes|required|numeric|min:0',
            'position_x' => 'nullable|integer',
            'position_y' => 'nullable|integer',
            'is_active' => 'boolean',
        ];
    }
}
