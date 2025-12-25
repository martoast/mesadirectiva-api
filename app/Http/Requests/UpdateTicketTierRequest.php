<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class UpdateTicketTierRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'name' => 'sometimes|required|string|max:255',
            'description' => 'nullable|string',
            'price' => 'sometimes|required|numeric|min:0',
            'early_bird_price' => 'nullable|numeric|min:0',
            'early_bird_deadline' => 'nullable|date',
            'max_quantity' => 'nullable|integer|min:1',
            'sort_order' => 'nullable|integer|min:0',
            'is_active' => 'boolean',
        ];
    }

    public function withValidator($validator): void
    {
        $validator->after(function ($validator) {
            $price = $this->input('price', $this->route('tier')->price ?? 0);
            $earlyBirdPrice = $this->input('early_bird_price');

            if ($earlyBirdPrice !== null && $earlyBirdPrice >= $price) {
                $validator->errors()->add('early_bird_price', 'Early bird price must be less than regular price.');
            }
        });
    }
}
