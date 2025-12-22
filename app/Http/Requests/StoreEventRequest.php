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
            // Basic Info (required)
            'category_id' => 'required|exists:categories,id',
            'name' => 'required|string|max:255',
            'description' => 'nullable|string',
            'date' => 'required|date|after_or_equal:today',
            'time' => 'required|date_format:H:i',
            'location' => 'required|string|max:255',
            'price' => 'required|numeric|min:0',
            'max_tickets' => 'required|integer|min:1',
            'registration_deadline' => 'nullable|date|after:now',

            // Hero Section (required)
            'hero_title' => 'required|string|max:255',
            'hero_subtitle' => 'required|string|max:500',
            'hero_image' => 'nullable|url|max:2000',
            'hero_cta_text' => 'nullable|string|max:50',

            // About Section (about is required, rest optional)
            'about' => 'required|string',
            'about_title' => 'nullable|string|max:255',
            'about_content' => 'nullable|string|max:10000',
            'about_image' => 'nullable|url|max:2000',
            'about_image_position' => 'nullable|in:left,right',

            // Highlights Section (optional)
            'highlights' => 'nullable|array|max:10',
            'highlights.*.icon' => 'nullable|string|max:50',
            'highlights.*.title' => 'required_with:highlights|string|max:100',
            'highlights.*.description' => 'nullable|string|max:500',

            // Schedule Section (optional)
            'schedule' => 'nullable|array|max:20',
            'schedule.*.time' => 'required_with:schedule|string|max:20',
            'schedule.*.title' => 'required_with:schedule|string|max:100',
            'schedule.*.description' => 'nullable|string|max:500',

            // Gallery Section (optional)
            'gallery_images' => 'nullable|array|max:20',
            'gallery_images.*' => 'url|max:2000',

            // FAQ Section (optional)
            'faq_items' => 'nullable|array|max:20',
            'faq_items.*.question' => 'required_with:faq_items|string|max:255',
            'faq_items.*.answer' => 'required_with:faq_items|string|max:2000',

            // Venue & Contact (optional)
            'venue_name' => 'nullable|string|max:255',
            'venue_address' => 'nullable|string|max:500',
            'venue_map_url' => 'nullable|url|max:2000',
            'contact_email' => 'nullable|email|max:255',
            'contact_phone' => 'nullable|string|max:30',
        ];
    }
}
