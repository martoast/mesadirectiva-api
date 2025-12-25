<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class Table extends Model
{
    use HasFactory;

    protected $fillable = [
        'event_id',
        'name',
        'capacity',
        'price',
        'sell_as_whole',
        'status',
        'position_x',
        'position_y',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'capacity' => 'integer',
            'price' => 'decimal:2',
            'sell_as_whole' => 'boolean',
            'position_x' => 'integer',
            'position_y' => 'integer',
            'is_active' => 'boolean',
        ];
    }

    // Relationships

    public function event(): BelongsTo
    {
        return $this->belongsTo(Event::class);
    }

    public function seats(): HasMany
    {
        return $this->hasMany(Seat::class);
    }

    public function activeSeats(): HasMany
    {
        return $this->hasMany(Seat::class)->where('is_active', true);
    }

    public function reservation(): HasOne
    {
        return $this->hasOne(TableReservation::class);
    }

    public function orderItems(): HasMany
    {
        return $this->hasMany(OrderItem::class);
    }

    // Business Logic

    public function isAvailable(): bool
    {
        return $this->is_active && $this->status === 'available';
    }

    public function isReserved(): bool
    {
        return $this->status === 'reserved';
    }

    public function isSold(): bool
    {
        return $this->status === 'sold';
    }

    public function getAvailableSeatsCount(): int
    {
        if ($this->sell_as_whole) {
            return 0; // Individual seats not available
        }
        return $this->activeSeats()->where('status', 'available')->count();
    }

    public function markAsReserved(): void
    {
        $this->update(['status' => 'reserved']);
    }

    public function markAsSold(): void
    {
        $this->update(['status' => 'sold']);
    }

    public function markAsAvailable(): void
    {
        $this->update(['status' => 'available']);
    }

    // Scopes

    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    public function scopeAvailable($query)
    {
        return $query->where('status', 'available');
    }

    public function scopeSellAsWhole($query)
    {
        return $query->where('sell_as_whole', true);
    }

    public function scopeWithIndividualSeats($query)
    {
        return $query->where('sell_as_whole', false);
    }
}
