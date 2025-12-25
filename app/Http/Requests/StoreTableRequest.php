<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreTableRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'name' => 'required|string|max:255',
            'capacity' => 'required|integer|min:1',
            'price' => 'required|numeric|min:0',
            'sell_as_whole' => 'boolean',
            'position_x' => 'nullable|integer',
            'position_y' => 'nullable|integer',
            'is_active' => 'boolean',
        ];
    }
}
