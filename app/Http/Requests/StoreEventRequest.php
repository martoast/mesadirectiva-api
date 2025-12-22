<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreEventRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('create', \App\Models\Event::class);
    }

    public function rules(): array
    {
        return [
            'category_id' => 'required|exists:categories,id',
            'name' => 'required|string|max:255',
            'description' => 'nullable|string',
            'date' => 'required|date|after_or_equal:today',
            'time' => 'required|date_format:H:i',
            'location' => 'required|string|max:255',
            'price' => 'required|numeric|min:0',
            'max_tickets' => 'required|integer|min:1',
            'hero_title' => 'required|string|max:255',
            'hero_subtitle' => 'required|string|max:500',
            'hero_image' => 'nullable|url|max:2000',
            'about' => 'required|string',
            'registration_deadline' => 'nullable|date|after:now',
        ];
    }
}
