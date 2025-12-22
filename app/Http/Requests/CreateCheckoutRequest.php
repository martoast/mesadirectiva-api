<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class CreateCheckoutRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'event_slug' => 'required|string|exists:events,slug',
            'customer_name' => 'required|string|max:255',
            'customer_email' => 'required|email|max:255',
            'customer_phone' => 'nullable|string|max:20',
            'tickets' => 'required|integer|min:1|max:10',
            'extra_items' => 'nullable|array',
            'extra_items.*.item_id' => 'required|integer|exists:event_items,id',
            'extra_items.*.quantity' => 'required|integer|min:1|max:10',
        ];
    }
}
