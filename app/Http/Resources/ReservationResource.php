<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ReservationResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'token' => $this->resource['token'],
            'expires_at' => $this->resource['expires_at'],
            'tables' => TableResource::collection($this->resource['tables'] ?? collect()),
            'seats' => SeatResource::collection($this->resource['seats'] ?? collect()),
        ];
    }
}
