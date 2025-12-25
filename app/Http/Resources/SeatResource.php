<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class SeatResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'table_id' => $this->table_id,
            'label' => $this->label,
            'status' => $this->status,
            'price' => (float) $this->price,
            'position_x' => $this->position_x,
            'position_y' => $this->position_y,
            'is_active' => $this->is_active,
            'is_available' => $this->isAvailable(),
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
        ];
    }
}
