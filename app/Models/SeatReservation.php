<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SeatReservation extends Model
{
    use HasFactory;

    protected $fillable = [
        'seat_id',
        'session_token',
        'order_id',
        'expires_at',
    ];

    protected function casts(): array
    {
        return [
            'expires_at' => 'datetime',
        ];
    }

    // Relationships

    public function seat(): BelongsTo
    {
        return $this->belongsTo(Seat::class);
    }

    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }

    // Business Logic

    public function isExpired(): bool
    {
        return now()->gte($this->expires_at);
    }

    public function isValid(): bool
    {
        return !$this->isExpired();
    }

    // Scopes

    public function scopeExpired($query)
    {
        return $query->where('expires_at', '<', now());
    }

    public function scopeValid($query)
    {
        return $query->where('expires_at', '>=', now());
    }

    public function scopeByToken($query, string $token)
    {
        return $query->where('session_token', $token);
    }
}
