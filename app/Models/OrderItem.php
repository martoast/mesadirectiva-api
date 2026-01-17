<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class OrderItem extends Model
{
    use HasFactory;

    protected $fillable = [
        'order_id',
        'item_type',
        'item_id',
        'ticket_tier_id',
        'seat_id',
        'table_id',
        'item_name',
        'quantity',
        'unit_price',
        'total_price',
        'attendee_name',
        'attendee_note',
        'checked_in_at',
        'checked_in_by',
    ];

    protected function casts(): array
    {
        return [
            'quantity' => 'integer',
            'unit_price' => 'decimal:2',
            'total_price' => 'decimal:2',
            'checked_in_at' => 'datetime',
        ];
    }

    // Check-in helpers

    public function isCheckedIn(): bool
    {
        return $this->checked_in_at !== null;
    }

    public function checkIn(int $userId): void
    {
        $this->update([
            'checked_in_at' => now(),
            'checked_in_by' => $userId,
        ]);
    }

    public function undoCheckIn(): void
    {
        $this->update([
            'checked_in_at' => null,
            'checked_in_by' => null,
        ]);
    }

    // Relationships

    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }

    public function eventItem(): BelongsTo
    {
        return $this->belongsTo(EventItem::class, 'item_id');
    }

    public function ticketTier(): BelongsTo
    {
        return $this->belongsTo(TicketTier::class);
    }

    public function seat(): BelongsTo
    {
        return $this->belongsTo(Seat::class);
    }

    public function table(): BelongsTo
    {
        return $this->belongsTo(Table::class);
    }

    public function checkedInBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'checked_in_by');
    }
}
