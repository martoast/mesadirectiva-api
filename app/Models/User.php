<?php

namespace App\Models;

use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;

class User extends Authenticatable implements MustVerifyEmail
{
    use HasApiTokens, HasFactory, Notifiable;

    protected $fillable = [
        'name',
        'email',
        'password',
        'phone',
        'preferred_language',
        'address_line_1',
        'address_line_2',
        'city',
        'state',
        'postal_code',
        'country',
        'provider',
        'provider_id',
        'google_id',
        'avatar',
        'role',
        'is_active',
        'user_type',
        'registration_source',
    ];

    protected $hidden = [
        'password',
        'remember_token',
        'provider_id',
    ];

    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'is_active' => 'boolean',
            'registration_source' => 'array',
        ];
    }

    // Relationships

    public function categories(): BelongsToMany
    {
        return $this->belongsToMany(Category::class)
            ->withPivot('permission')
            ->withTimestamps();
    }

    public function createdEvents(): HasMany
    {
        return $this->hasMany(Event::class, 'created_by');
    }

    public function createdCategories(): HasMany
    {
        return $this->hasMany(Category::class, 'created_by');
    }

    // Role Helpers

    public function isSuperAdmin(): bool
    {
        return $this->role === 'super_admin';
    }

    public function isAdmin(): bool
    {
        return in_array($this->role, ['super_admin', 'admin']);
    }

    public function isViewer(): bool
    {
        return $this->role === 'viewer';
    }

    // Category Permission Helpers

    public function hasAccessToCategory(int $categoryId): bool
    {
        if ($this->isSuperAdmin()) {
            return true;
        }

        return $this->categories()->where('category_id', $categoryId)->exists();
    }

    public function getCategoryPermission(int $categoryId): ?string
    {
        if ($this->isSuperAdmin()) {
            return 'manage';
        }

        $category = $this->categories()->where('category_id', $categoryId)->first();
        return $category?->pivot?->permission;
    }

    public function canEditCategory(int $categoryId): bool
    {
        $permission = $this->getCategoryPermission($categoryId);
        return in_array($permission, ['edit', 'manage']);
    }

    public function canManageCategory(int $categoryId): bool
    {
        return $this->getCategoryPermission($categoryId) === 'manage';
    }

    // Address Helper

    public function hasCompleteAddress(): bool
    {
        return $this->address_line_1
            && $this->city
            && $this->state
            && $this->postal_code
            && $this->country;
    }

    public function getAddressAttribute(): ?array
    {
        if (!$this->hasCompleteAddress()) {
            return null;
        }

        return [
            'line_1' => $this->address_line_1,
            'line_2' => $this->address_line_2,
            'city' => $this->city,
            'state' => $this->state,
            'postal_code' => $this->postal_code,
            'country' => $this->country,
        ];
    }
}
