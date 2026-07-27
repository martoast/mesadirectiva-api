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
            // Core Info
            'group_id' => 'sometimes|exists:groups,id',
            'name' => 'sometimes|string|max:255',
            'description' => 'nullable|string',
            'image' => 'nullable|url|max:2000',
            'image_focal_x' => 'sometimes|numeric|min:0|max:100',
            'image_focal_y' => 'sometimes|numeric|min:0|max:100',

            // Date/Time
            'starts_at' => 'sometimes|date',
            'ends_at' => 'sometimes|date|after:starts_at',
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

            // Stripe account routing
            'stripe_account' => 'sometimes|in:cafeteria,rifa,eventos',

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

            // Checkout field configuration
            'checkout_settings' => 'nullable|array',
            'checkout_settings.collect_student_fields' => 'nullable|boolean',
            'checkout_settings.require_student_fields' => 'nullable|boolean',
            'checkout_settings.require_attendee_note' => 'nullable|boolean',

            // Email & Ticket customization
            'email_settings' => 'nullable|array',
            'email_settings.email_subject' => 'nullable|string|max:255',
            'email_settings.email_greeting' => 'nullable|string|max:5000',
            'email_settings.email_intro' => 'nullable|string|max:5000',
            'email_settings.email_instructions' => 'nullable|string|max:5000',
            'email_settings.email_footer' => 'nullable|string|max:5000',
            'email_settings.ticket_footer' => 'nullable|string|max:5000',
        ];
    }
}
