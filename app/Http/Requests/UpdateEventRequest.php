<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class UpdateEventRequest extends FormRequest
{
    public function authorize(): bool
    {
        // Authorization is handled in the controller after fetching the event by slug
        return true;
    }

    public function rules(): array
    {
        return [
            'category_id' => 'sometimes|exists:categories,id',
            'name' => 'sometimes|string|max:255',
            'description' => 'nullable|string',
            'date' => 'sometimes|date',
            'time' => 'sometimes|date_format:H:i',
            'location' => 'sometimes|string|max:255',
            'price' => 'sometimes|numeric|min:0',
            'max_tickets' => 'sometimes|integer|min:1',
            'hero_title' => 'sometimes|string|max:255',
            'hero_subtitle' => 'sometimes|string|max:500',
            'hero_image' => 'nullable|url|max:2000',
            'about' => 'sometimes|string',
            'registration_deadline' => 'nullable|date',
        ];
    }
}
