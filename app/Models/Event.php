<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Str;

class Event extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'slug',
        'category_id',
        'name',
        'description',
        'date',
        'time',
        'location',
        'price',
        'max_tickets',
        'tickets_sold',
        'status',
        'registration_open',
        'registration_deadline',
        'hero_title',
        'hero_subtitle',
        'hero_image',
        'about',
        'stripe_product_id',
        'stripe_price_id',
        'created_by',
    ];

    protected function casts(): array
    {
        return [
            'date' => 'date',
            'price' => 'decimal:2',
            'max_tickets' => 'integer',
            'tickets_sold' => 'integer',
            'registration_open' => 'boolean',
            'registration_deadline' => 'datetime',
        ];
    }

    // Auto-generate slug on create
    protected static function boot()
    {
        parent::boot();

        static::creating(function ($event) {
            if (empty($event->slug)) {
                $event->slug = static::generateUniqueSlug($event->name);
            }
        });
    }

    public static function generateUniqueSlug(string $name): string
    {
        $slug = Str::slug($name);
        $originalSlug = $slug;
        $counter = 1;

        while (static::withTrashed()->where('slug', $slug)->exists()) {
            $slug = $originalSlug . '-' . $counter;
            $counter++;
        }

        return $slug;
    }

    // Relationships

    public function category(): BelongsTo
    {
        return $this->belongsTo(Category::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function items(): HasMany
    {
        return $this->hasMany(EventItem::class);
    }

    public function activeItems(): HasMany
    {
        return $this->hasMany(EventItem::class)->where('is_active', true);
    }

    public function orders(): HasMany
    {
        return $this->hasMany(Order::class);
    }

    public function completedOrders(): HasMany
    {
        return $this->hasMany(Order::class)->where('status', 'completed');
    }

    // Business Logic

    public function canPurchase(): bool
    {
        if ($this->status !== 'live') {
            return false;
        }

        if (!$this->registration_open) {
            return false;
        }

        if ($this->registration_deadline && now()->isAfter($this->registration_deadline)) {
            return false;
        }

        if ($this->tickets_sold >= $this->max_tickets) {
            return false;
        }

        return true;
    }

    public function getPurchaseBlockedReason(): ?string
    {
        if ($this->status !== 'live') {
            return 'not_live';
        }

        if (!$this->registration_open) {
            return 'registration_closed';
        }

        if ($this->registration_deadline && now()->isAfter($this->registration_deadline)) {
            return 'deadline_passed';
        }

        if ($this->tickets_sold >= $this->max_tickets) {
            return 'sold_out';
        }

        return null;
    }

    public function getTicketsAvailable(): int
    {
        return max(0, $this->max_tickets - $this->tickets_sold);
    }

    public function getRevenue(): float
    {
        return (float) $this->completedOrders()->sum('total');
    }

    // Scopes

    public function scopeLive($query)
    {
        return $query->where('status', 'live');
    }

    public function scopeAccessibleBy($query, User $user)
    {
        if ($user->isSuperAdmin()) {
            return $query;
        }

        $categoryIds = $user->categories()->pluck('categories.id');

        return $query->whereIn('category_id', $categoryIds);
    }
}
