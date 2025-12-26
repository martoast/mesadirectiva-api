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
            // Core Info (required)
            'group_id' => 'required|exists:groups,id',
            'name' => 'required|string|max:255',
            'description' => 'nullable|string',
            'image' => 'nullable|url|max:2000',

            // Date/Time (required)
            'starts_at' => 'required|date|after_or_equal:now',
            'ends_at' => 'required|date|after:starts_at',
            'timezone' => 'sometimes|string|timezone',

            // Location
            'location_type' => 'sometimes|in:venue,online',
            'location' => 'nullable|array',
            'location.name' => 'nullable|string|max:255',
            'location.address' => 'nullable|string|max:500',
            'location.city' => 'nullable|string|max:100',
            'location.state' => 'nullable|string|max:100',
            'location.country' => 'nullable|string|max:100',
            'location.postal_code' => 'nullable|string|max:20',
            'location.map_url' => 'nullable|url|max:2000',
            // Online event fields
            'location.platform' => 'nullable|string|max:100',
            'location.url' => 'nullable|url|max:2000',
            'location.instructions' => 'nullable|string|max:1000',

            // Event Type
            'seating_type' => 'sometimes|in:general_admission,seated',
            'reservation_minutes' => 'sometimes|integer|min:5|max:60',

            // Settings
            'is_private' => 'sometimes|boolean',
            'show_remaining' => 'sometimes|boolean',

            // Organizer
            'organizer_name' => 'nullable|string|max:255',
            'organizer_description' => 'nullable|string|max:2000',

            // FAQ (optional)
            'faq_items' => 'nullable|array|max:20',
            'faq_items.*.question' => 'required_with:faq_items|string|max:255',
            'faq_items.*.answer' => 'required_with:faq_items|string|max:2000',
        ];
    }
}
