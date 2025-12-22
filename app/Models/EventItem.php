<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class EventItem extends Model
{
    use HasFactory;

    protected $fillable = [
        'event_id',
        'name',
        'description',
        'price',
        'max_quantity',
        'quantity_sold',
        'is_active',
        'stripe_price_id',
    ];

    protected function casts(): array
    {
        return [
            'price' => 'decimal:2',
            'max_quantity' => 'integer',
            'quantity_sold' => 'integer',
            'is_active' => 'boolean',
        ];
    }

    // Relationships

    public function event(): BelongsTo
    {
        return $this->belongsTo(Event::class);
    }

    // Business Logic

    public function getAvailableQuantity(): ?int
    {
        if ($this->max_quantity === null) {
            return null; // Unlimited
        }

        return max(0, $this->max_quantity - $this->quantity_sold);
    }

    public function isAvailable(): bool
    {
        if (!$this->is_active) {
            return false;
        }

        if ($this->max_quantity === null) {
            return true;
        }

        return $this->quantity_sold < $this->max_quantity;
    }
}
