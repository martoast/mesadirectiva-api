<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class AttendeeResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'attendee_name' => $this->attendee_name,
            'attendee_note' => $this->attendee_note,
            'item_type' => $this->item_type,
            'item_name' => $this->item_name,
            'quantity' => $this->quantity,
            'unit_price' => (float) $this->unit_price,
            'total_price' => (float) $this->total_price,
            'ticket_code' => $this->ticket_code,

            // Check-in status
            'is_checked_in' => $this->isCheckedIn(),
            'checked_in_at' => $this->checked_in_at,
            'checked_in_by' => $this->whenLoaded('checkedInBy', function () {
                return [
                    'id' => $this->checkedInBy->id,
                    'name' => $this->checkedInBy->name,
                ];
            }),

            // Ticket tier info (for GA events)
            'tier' => $this->whenLoaded('ticketTier', function () {
                return $this->ticketTier ? [
                    'id' => $this->ticketTier->id,
                    'name' => $this->ticketTier->name,
                ] : null;
            }),

            // Table info (for seated events)
            'table' => $this->whenLoaded('table', function () {
                return $this->table ? [
                    'id' => $this->table->id,
                    'name' => $this->table->name,
                    'capacity' => $this->table->capacity,
                ] : null;
            }),

            // Seat info (for seated events)
            'seat' => $this->whenLoaded('seat', function () {
                if (!$this->seat) return null;
                return [
                    'id' => $this->seat->id,
                    'label' => $this->seat->label,
                    'table' => $this->seat->table ? [
                        'id' => $this->seat->table->id,
                        'name' => $this->seat->table->name,
                    ] : null,
                ];
            }),

            // Order info (buyer)
            'order' => $this->whenLoaded('order', function () {
                return [
                    'id' => $this->order->id,
                    'order_number' => $this->order->order_number,
                    'customer_name' => $this->order->customer_name,
                    'customer_email' => $this->order->customer_email,
                    'customer_phone' => $this->order->customer_phone,
                    'status' => $this->order->status,
                    'paid_at' => $this->order->paid_at,
                ];
            }),

            'created_at' => $this->created_at,
        ];
    }
}
