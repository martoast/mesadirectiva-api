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

            // Inventory
            'quantity' => $this->quantity,
            'quantity_sold' => $this->quantity_sold,
            'available' => $this->getAvailableQuantity(),

            // Sales Window
            'sales_start' => $this->sales_start,
            'sales_end' => $this->sales_end,
            'sales_status' => $this->getSalesStatus(),
            'is_on_sale' => $this->isOnSale(),

            // Per-order limits
            'min_per_order' => $this->min_per_order,
            'max_per_order' => $this->max_per_order,

            // Display options
            'show_description' => $this->show_description,
            'is_hidden' => $this->is_hidden,
            'sort_order' => $this->sort_order,
            'is_active' => $this->is_active,

            // Computed
            'is_available' => $this->isAvailable(),
            'is_sold_out' => $this->isSoldOut(),

            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
        ];
    }
}
