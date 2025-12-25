<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreTicketTierRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'name' => 'required|string|max:255',
            'description' => 'nullable|string',
            'price' => 'required|numeric|min:0',
            'early_bird_price' => 'nullable|numeric|min:0|lt:price',
            'early_bird_deadline' => 'nullable|date|after:now',
            'max_quantity' => 'nullable|integer|min:1',
            'sort_order' => 'nullable|integer|min:0',
            'is_active' => 'boolean',
        ];
    }

    public function messages(): array
    {
        return [
            'early_bird_price.lt' => 'Early bird price must be less than regular price.',
            'early_bird_deadline.after' => 'Early bird deadline must be in the future.',
        ];
    }
}
