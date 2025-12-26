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
        'group_id',
        'name',
        'description',
        'image',
        // Date/Time
        'starts_at',
        'ends_at',
        'timezone',
        // Location
        'location_type',
        'location',
        // Media gallery
        'media',
        // Event type
        'seating_type',
        'reservation_minutes',
        // Settings
        'status',
        'is_private',
        'show_remaining',
        // Organizer
        'organizer_name',
        'organizer_description',
        // Content
        'faq_items',
        // Stripe
        'stripe_product_id',
        'stripe_price_id',
        'created_by',
    ];

    protected function casts(): array
    {
        return [
            'starts_at' => 'datetime',
            'ends_at' => 'datetime',
            'location' => 'array',
            'media' => 'array',
            'faq_items' => 'array',
            'reservation_minutes' => 'integer',
            'is_private' => 'boolean',
            'show_remaining' => 'boolean',
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

    public function group(): BelongsTo
    {
        return $this->belongsTo(Group::class);
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

    public function ticketTiers(): HasMany
    {
        return $this->hasMany(TicketTier::class);
    }

    public function activeTicketTiers(): HasMany
    {
        return $this->hasMany(TicketTier::class)
            ->where('is_active', true)
            ->orderBy('sort_order')
            ->orderBy('id');
    }

    public function availableTicketTiers(): HasMany
    {
        $now = now();
        return $this->hasMany(TicketTier::class)
            ->where('is_active', true)
            ->where('is_hidden', false)
            ->where(function ($query) use ($now) {
                $query->whereNull('sales_start')
                    ->orWhere('sales_start', '<=', $now);
            })
            ->where(function ($query) use ($now) {
                $query->whereNull('sales_end')
                    ->orWhere('sales_end', '>=', $now);
            })
            ->orderBy('sort_order')
            ->orderBy('id');
    }

    public function tables(): HasMany
    {
        return $this->hasMany(Table::class);
    }

    public function activeTables(): HasMany
    {
        return $this->hasMany(Table::class)->where('is_active', true);
    }

    // Business Logic

    public function isSeated(): bool
    {
        return $this->seating_type === 'seated';
    }

    public function isGeneralAdmission(): bool
    {
        return $this->seating_type === 'general_admission';
    }

    public function isOnline(): bool
    {
        return $this->location_type === 'online';
    }

    public function isVenue(): bool
    {
        return $this->location_type === 'venue';
    }

    public function canPurchase(): bool
    {
        if ($this->status !== 'live') {
            return false;
        }

        // Check if any ticket tiers are available
        return $this->availableTicketTiers()->exists();
    }

    public function getPurchaseBlockedReason(): ?string
    {
        if ($this->status !== 'live') {
            return 'not_live';
        }

        if (!$this->availableTicketTiers()->exists()) {
            return 'no_available_tickets';
        }

        return null;
    }

    public function getTotalTicketsAvailable(): int
    {
        return $this->activeTicketTiers->sum(fn ($tier) => $tier->getAvailableQuantity() ?? PHP_INT_MAX);
    }

    public function getTotalTicketsSold(): int
    {
        return $this->activeTicketTiers->sum('quantity_sold');
    }

    public function getRevenue(): float
    {
        return (float) $this->completedOrders()->sum('total');
    }

    // Media helpers

    public function getImages(): array
    {
        return $this->media['images'] ?? [];
    }

    public function getVideos(): array
    {
        return $this->media['videos'] ?? [];
    }

    public function addImage(array $image): void
    {
        $media = $this->media ?? ['images' => [], 'videos' => []];
        $media['images'][] = $image;
        $this->media = $media;
        $this->save();
    }

    public function addVideo(array $video): void
    {
        $media = $this->media ?? ['images' => [], 'videos' => []];
        $media['videos'][] = $video;
        $this->media = $media;
        $this->save();
    }

    public function removeMediaItem(string $type, int $index): void
    {
        $media = $this->media ?? ['images' => [], 'videos' => []];
        if (isset($media[$type][$index])) {
            array_splice($media[$type], $index, 1);
            $this->media = $media;
            $this->save();
        }
    }

    // Location helpers

    public function getLocationName(): ?string
    {
        if ($this->isOnline()) {
            return $this->location['platform'] ?? 'Online Event';
        }
        return $this->location['name'] ?? null;
    }

    public function getLocationAddress(): ?string
    {
        if ($this->isOnline()) {
            return null;
        }
        $location = $this->location;
        if (!$location) {
            return null;
        }
        $parts = array_filter([
            $location['address'] ?? null,
            $location['city'] ?? null,
            $location['state'] ?? null,
            $location['postal_code'] ?? null,
        ]);
        return implode(', ', $parts);
    }

    // Scopes

    public function scopeLive($query)
    {
        return $query->where('status', 'live');
    }

    public function scopePublic($query)
    {
        return $query->where('is_private', false);
    }

    public function scopeUpcoming($query)
    {
        return $query->where('starts_at', '>=', now());
    }

    public function scopeAccessibleBy($query, User $user)
    {
        if ($user->isSuperAdmin()) {
            return $query;
        }

        $groupIds = $user->groups()->pluck('groups.id');

        return $query->whereIn('group_id', $groupIds);
    }
}
