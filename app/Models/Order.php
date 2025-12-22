<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;

class Order extends Model
{
    use HasFactory;

    protected $fillable = [
        'order_number',
        'event_id',
        'customer_name',
        'customer_email',
        'customer_phone',
        'status',
        'subtotal',
        'total',
        'stripe_checkout_session_id',
        'stripe_payment_intent_id',
        'paid_at',
    ];

    protected function casts(): array
    {
        return [
            'subtotal' => 'decimal:2',
            'total' => 'decimal:2',
            'paid_at' => 'datetime',
        ];
    }

    protected static function boot()
    {
        parent::boot();

        static::creating(function ($order) {
            if (empty($order->order_number)) {
                $order->order_number = static::generateOrderNumber();
            }
        });
    }

    public static function generateOrderNumber(): string
    {
        $prefix = 'ORD';
        $timestamp = now()->format('ymd');
        $random = strtoupper(Str::random(4));

        $orderNumber = "{$prefix}-{$timestamp}-{$random}";

        while (static::where('order_number', $orderNumber)->exists()) {
            $random = strtoupper(Str::random(4));
            $orderNumber = "{$prefix}-{$timestamp}-{$random}";
        }

        return $orderNumber;
    }

    // Relationships

    public function event(): BelongsTo
    {
        return $this->belongsTo(Event::class);
    }

    public function items(): HasMany
    {
        return $this->hasMany(OrderItem::class);
    }

    // Helper Methods

    public function getTicketCount(): int
    {
        return (int) $this->items()
            ->where('item_type', 'ticket')
            ->sum('quantity');
    }

    public function markAsCompleted(string $paymentIntentId): void
    {
        $this->update([
            'status' => 'completed',
            'stripe_payment_intent_id' => $paymentIntentId,
            'paid_at' => now(),
        ]);
    }

    public function markAsFailed(): void
    {
        $this->update(['status' => 'failed']);
    }

    public function markAsRefunded(): void
    {
        $this->update(['status' => 'refunded']);
    }
}
