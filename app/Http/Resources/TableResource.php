<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class TableResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'capacity' => $this->capacity,
            'price' => (float) $this->price,
            'sell_as_whole' => $this->sell_as_whole,
            'status' => $this->status,
            'position_x' => $this->position_x,
            'position_y' => $this->position_y,
            'is_active' => $this->is_active,
            'is_available' => $this->isAvailable(),
            'seats_count' => $this->when(!$this->sell_as_whole, fn() => $this->seats()->count()),
            'seats_available' => $this->when(!$this->sell_as_whole, fn() => $this->getAvailableSeatsCount()),
            'seats' => SeatResource::collection($this->whenLoaded('seats')),
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
        ];
    }
}
