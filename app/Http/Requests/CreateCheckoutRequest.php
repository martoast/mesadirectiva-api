<?php

namespace App\Http\Requests;

use App\Models\Event;
use Illuminate\Foundation\Http\FormRequest;

class CreateCheckoutRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $rules = [
            'event_slug' => 'required|string|exists:events,slug',
            'customer_name' => 'required|string|max:255',
            'customer_email' => 'required|email|max:255',
            'customer_phone' => 'nullable|string|max:20',
            'customer_company' => 'nullable|string|max:255',
            'extra_items' => 'nullable|array',
            'extra_items.*.item_id' => 'required|integer|exists:event_items,id',
            'extra_items.*.quantity' => 'required|integer|min:1|max:10',
        ];

        // Determine event type if we have the slug
        $event = Event::where('slug', $this->input('event_slug'))->first();

        if ($event && $event->isSeated()) {
            // Seated event rules - tables and seats as objects with attendee info
            $rules['tables'] = 'nullable|array';
            $rules['tables.*.id'] = 'required|integer|exists:tables,id';
            $rules['tables.*.attendee_name'] = 'nullable|string|max:255';
            $rules['tables.*.attendee_note'] = 'nullable|string|max:500';
            $rules['seats'] = 'nullable|array';
            $rules['seats.*.id'] = 'required|integer|exists:seats,id';
            $rules['seats.*.attendee_name'] = 'nullable|string|max:255';
            $rules['seats.*.attendee_note'] = 'nullable|string|max:500';
            $rules['reservation_token'] = 'required|string';
        } else {
            // General admission rules - tiers with optional attendee info per ticket
            $rules['tiers'] = 'nullable|array';
            $rules['tiers.*.tier_id'] = 'required|integer|exists:ticket_tiers,id';
            $rules['tiers.*.quantity'] = 'required|integer|min:1|max:10';
            $rules['tiers.*.attendees'] = 'nullable|array';
            $rules['tiers.*.attendees.*.name'] = 'nullable|string|max:255';
            $rules['tiers.*.attendees.*.note'] = 'nullable|string|max:500';
            // Keep legacy support for simple tickets
            $rules['tickets'] = 'nullable|integer|min:1|max:10';
        }

        return $rules;
    }

    public function withValidator($validator): void
    {
        $validator->after(function ($validator) {
            $event = Event::where('slug', $this->input('event_slug'))->first();

            if (!$event) {
                return;
            }

            if ($event->isSeated()) {
                $tables = $this->input('tables', []);
                $seats = $this->input('seats', []);

                // Extract IDs from objects
                $tableIds = array_column($tables, 'id');
                $seatIds = array_column($seats, 'id');

                if (empty($tableIds) && empty($seatIds)) {
                    $validator->errors()->add('tables', 'You must select at least one table or seat.');
                }
            } else {
                $tiers = $this->input('tiers', []);
                $tickets = $this->input('tickets');

                if (empty($tiers) && !$tickets) {
                    $validator->errors()->add('tiers', 'You must select at least one ticket tier or specify tickets.');
                }
            }
        });
    }
}
