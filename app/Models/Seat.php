<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class Seat extends Model
{
    use HasFactory;

    protected $fillable = [
        'table_id',
        'label',
        'status',
        'price',
        'position_x',
        'position_y',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'price' => 'decimal:2',
            'position_x' => 'integer',
            'position_y' => 'integer',
            'is_active' => 'boolean',
        ];
    }

    // Relationships

    public function table(): BelongsTo
    {
        return $this->belongsTo(Table::class);
    }

    public function reservation(): HasOne
    {
        return $this->hasOne(SeatReservation::class);
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
}
