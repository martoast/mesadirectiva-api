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
            'extra_items' => 'nullable|array',
            'extra_items.*.item_id' => 'required|integer|exists:event_items,id',
            'extra_items.*.quantity' => 'required|integer|min:1|max:10',
        ];

        // Determine event type if we have the slug
        $event = Event::where('slug', $this->input('event_slug'))->first();

        if ($event && $event->isSeated()) {
            // Seated event rules
            $rules['tables'] = 'nullable|array';
            $rules['tables.*'] = 'integer|exists:tables,id';
            $rules['seats'] = 'nullable|array';
            $rules['seats.*'] = 'integer|exists:seats,id';
            $rules['reservation_token'] = 'required|string';
        } else {
            // General admission rules
            $rules['tiers'] = 'nullable|array';
            $rules['tiers.*.tier_id'] = 'required|integer|exists:ticket_tiers,id';
            $rules['tiers.*.quantity'] = 'required|integer|min:1|max:10';
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

                if (empty($tables) && empty($seats)) {
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
