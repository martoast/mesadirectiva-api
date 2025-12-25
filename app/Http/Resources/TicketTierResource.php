<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class TicketTierResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'description' => $this->description,
            'price' => (float) $this->price,
            'early_bird_price' => $this->early_bird_price ? (float) $this->early_bird_price : null,
            'early_bird_deadline' => $this->early_bird_deadline,
            'current_price' => $this->getCurrentPrice(),
            'is_early_bird' => $this->isEarlyBird(),
            'max_quantity' => $this->max_quantity,
            'quantity_sold' => $this->quantity_sold,
            'available' => $this->getAvailableQuantity(),
            'is_available' => $this->isAvailable(),
            'sort_order' => $this->sort_order,
            'is_active' => $this->is_active,
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
        ];
    }
}
