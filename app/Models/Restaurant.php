<?php

namespace App\Models;

use App\Enums\RestaurantStatus;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphMany;

class Restaurant extends Model
{
    use HasFactory;

    /**
     * Columns that may be mass assigned.
     *
     * @var list<string>
     */
    protected $fillable = [
        'user_id',      // the restaurant_owner who owns this restaurant
        'name',
        'description',
        'address',
        'lat',          // latitude  (for Google Maps)
        'lng',          // longitude (for Google Maps)
        'logo',
        'banner',
        'is_open',
        'rating',
        'status',       // moderation state (pending/approved/rejected)
        'approved_at',
    ];

    /**
     * Automatic type conversions.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'lat' => 'float',
            'lng' => 'float',
            'is_open' => 'boolean',
            'rating' => 'float',
            'status' => RestaurantStatus::class,
            'approved_at' => 'datetime',
        ];
    }

    /**
     * Get the logo attribute as a full URL if it is stored as a relative path.
     */
    public function getLogoAttribute(?string $value): ?string
    {
        if (empty($value)) {
            return null;
        }

        if (str_starts_with($value, 'http://') || str_starts_with($value, 'https://')) {
            return $value;
        }

        return asset('storage/' . $value);
    }

    /**
     * Get the banner attribute as a full URL if it is stored as a relative path.
     */
    public function getBannerAttribute(?string $value): ?string
    {
        if (empty($value)) {
            return null;
        }

        if (str_starts_with($value, 'http://') || str_starts_with($value, 'https://')) {
            return $value;
        }

        return asset('storage/' . $value);
    }

    /**
     * Payouts owed to this restaurant.
     */
    public function payouts(): MorphMany
    {
        return $this->morphMany(Payout::class, 'payee');
    }

    /**
     * Approve this restaurant so it becomes visible to customers.
     */
    public function approve(): void
    {
        $this->update([
            'status'      => RestaurantStatus::APPROVED,
            'approved_at' => now(),
        ]);
    }

    /**
     * Limit a query to restaurants that are live (approved).
     */
    public function scopeApproved($query)
    {
        return $query->where('status', RestaurantStatus::APPROVED);
    }

    /**
     * The user (restaurant_owner) that owns this restaurant.
     */
    public function owner(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    /**
     * All menu items belonging to this restaurant.
     */
    public function menuItems(): HasMany
    {
        return $this->hasMany(MenuItem::class);
    }

    /**
     * All orders placed at this restaurant.
     */
    public function orders(): HasMany
    {
        return $this->hasMany(Order::class);
    }
}
