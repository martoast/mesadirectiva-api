<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class EventItemResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'description' => $this->description,
            'price' => (float) $this->price,
            'max_quantity' => $this->max_quantity,
            'quantity_sold' => $this->quantity_sold,
            'available_quantity' => $this->getAvailableQuantity(),
            'is_active' => $this->is_active,
            'is_available' => $this->isAvailable(),
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
        ];
    }
}
