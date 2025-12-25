<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class TicketTier extends Model
{
    use HasFactory;

    protected $fillable = [
        'event_id',
        'name',
        'description',
        'price',
        'early_bird_price',
        'early_bird_deadline',
        'max_quantity',
        'quantity_sold',
        'sort_order',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'price' => 'decimal:2',
            'early_bird_price' => 'decimal:2',
            'early_bird_deadline' => 'datetime',
            'max_quantity' => 'integer',
            'quantity_sold' => 'integer',
            'sort_order' => 'integer',
            'is_active' => 'boolean',
        ];
    }

    // Relationships

    public function event(): BelongsTo
    {
        return $this->belongsTo(Event::class);
    }

    public function orderItems(): HasMany
    {
        return $this->hasMany(OrderItem::class);
    }

    // Business Logic

    public function getCurrentPrice(): float
    {
        if ($this->early_bird_price
            && $this->early_bird_deadline
            && now()->lt($this->early_bird_deadline)) {
            return (float) $this->early_bird_price;
        }
        return (float) $this->price;
    }

    public function isEarlyBird(): bool
    {
        return $this->early_bird_price
            && $this->early_bird_deadline
            && now()->lt($this->early_bird_deadline);
    }

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

        $available = $this->getAvailableQuantity();
        return $available === null || $available > 0;
    }

    // Scopes

    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    public function scopeOrdered($query)
    {
        return $query->orderBy('sort_order')->orderBy('id');
    }
}
