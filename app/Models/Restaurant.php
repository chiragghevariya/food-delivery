<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

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
        ];
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
