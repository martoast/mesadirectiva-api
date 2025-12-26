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
        'quantity',
        'quantity_sold',
        // Sales window (Eventbrite-style)
        'sales_start',
        'sales_end',
        // Per-order limits
        'min_per_order',
        'max_per_order',
        // Display options
        'show_description',
        'is_hidden',
        'sort_order',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'price' => 'decimal:2',
            'quantity' => 'integer',
            'quantity_sold' => 'integer',
            'sales_start' => 'datetime',
            'sales_end' => 'datetime',
            'min_per_order' => 'integer',
            'max_per_order' => 'integer',
            'show_description' => 'boolean',
            'is_hidden' => 'boolean',
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

    public function getAvailableQuantity(): ?int
    {
        if ($this->quantity === null) {
            return null; // Unlimited
        }
        return max(0, $this->quantity - $this->quantity_sold);
    }

    public function isOnSale(): bool
    {
        $now = now();

        if ($this->sales_start && $now->lt($this->sales_start)) {
            return false; // Sales haven't started
        }

        if ($this->sales_end && $now->gt($this->sales_end)) {
            return false; // Sales have ended
        }

        return true;
    }

    public function isSoldOut(): bool
    {
        $available = $this->getAvailableQuantity();
        return $available !== null && $available <= 0;
    }

    public function isAvailable(): bool
    {
        if (!$this->is_active) {
            return false;
        }

        if ($this->is_hidden) {
            return false;
        }

        if (!$this->isOnSale()) {
            return false;
        }

        if ($this->isSoldOut()) {
            return false;
        }

        return true;
    }

    public function getSalesStatus(): string
    {
        if (!$this->is_active) {
            return 'inactive';
        }

        if ($this->is_hidden) {
            return 'hidden';
        }

        $now = now();

        if ($this->sales_start && $now->lt($this->sales_start)) {
            return 'scheduled';
        }

        if ($this->sales_end && $now->gt($this->sales_end)) {
            return 'ended';
        }

        if ($this->isSoldOut()) {
            return 'sold_out';
        }

        return 'on_sale';
    }

    public function getTimeUntilSaleStarts(): ?int
    {
        if (!$this->sales_start) {
            return null;
        }

        $now = now();
        if ($now->gte($this->sales_start)) {
            return 0;
        }

        return $now->diffInSeconds($this->sales_start);
    }

    public function getTimeUntilSaleEnds(): ?int
    {
        if (!$this->sales_end) {
            return null;
        }

        $now = now();
        if ($now->gte($this->sales_end)) {
            return 0;
        }

        return $now->diffInSeconds($this->sales_end);
    }

    public function getCurrentPrice(): float
    {
        return (float) $this->price;
    }

    // Scopes

    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    public function scopeVisible($query)
    {
        return $query->where('is_hidden', false);
    }

    public function scopeOnSale($query)
    {
        $now = now();
        return $query
            ->where(function ($q) use ($now) {
                $q->whereNull('sales_start')
                    ->orWhere('sales_start', '<=', $now);
            })
            ->where(function ($q) use ($now) {
                $q->whereNull('sales_end')
                    ->orWhere('sales_end', '>=', $now);
            });
    }

    public function scopeOrdered($query)
    {
        return $query->orderBy('sort_order')->orderBy('id');
    }
}
