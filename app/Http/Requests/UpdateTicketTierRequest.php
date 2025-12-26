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
            'quantity' => 'nullable|integer|min:1',

            // Sales window
            'sales_start' => 'nullable|date',
            'sales_end' => 'nullable|date',

            // Per-order limits
            'min_per_order' => 'sometimes|integer|min:1',
            'max_per_order' => 'sometimes|integer|min:1',

            // Display options
            'show_description' => 'boolean',
            'is_hidden' => 'boolean',
            'sort_order' => 'nullable|integer|min:0',
            'is_active' => 'boolean',
        ];
    }

    public function withValidator($validator): void
    {
        $validator->after(function ($validator) {
            $salesStart = $this->input('sales_start');
            $salesEnd = $this->input('sales_end');

            if ($salesStart && $salesEnd && strtotime($salesEnd) <= strtotime($salesStart)) {
                $validator->errors()->add('sales_end', 'Sales end date must be after sales start date.');
            }

            $minPerOrder = $this->input('min_per_order', 1);
            $maxPerOrder = $this->input('max_per_order');

            if ($maxPerOrder !== null && $maxPerOrder < $minPerOrder) {
                $validator->errors()->add('max_per_order', 'Maximum per order must be greater than or equal to minimum per order.');
            }
        });
    }
}
