<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class OrderItemResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'item_type' => $this->item_type,
            'item_id' => $this->item_id,
            'item_name' => $this->item_name,
            'quantity' => $this->quantity,
            'unit_price' => (float) $this->unit_price,
            'total_price' => (float) $this->total_price,
            'ticket_tier_id' => $this->ticket_tier_id,
            'seat_id' => $this->seat_id,
            'table_id' => $this->table_id,
            'ticket_tier' => new TicketTierResource($this->whenLoaded('ticketTier')),
            'seat' => new SeatResource($this->whenLoaded('seat')),
            'table' => new TableResource($this->whenLoaded('table')),
        ];
    }
}
