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
            'quantity' => 'nullable|integer|min:1',

            // Sales window
            'sales_start' => 'nullable|date',
            'sales_end' => 'nullable|date|after:sales_start',

            // Per-order limits
            'min_per_order' => 'sometimes|integer|min:1',
            'max_per_order' => 'sometimes|integer|min:1|gte:min_per_order',

            // Display options
            'show_description' => 'boolean',
            'is_hidden' => 'boolean',
            'sort_order' => 'nullable|integer|min:0',
            'is_active' => 'boolean',
        ];
    }

    public function messages(): array
    {
        return [
            'sales_end.after' => 'Sales end date must be after sales start date.',
            'max_per_order.gte' => 'Maximum per order must be greater than or equal to minimum per order.',
        ];
    }
}
