<?php

namespace App\Models;

use Filament\Models\Contracts\FilamentUser;   // lets us decide who can open each Filament panel
use Filament\Panel;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Spatie\Permission\Traits\HasRoles;       // gives the user ->assignRole(), ->hasRole(), etc.
use Tymon\JWTAuth\Contracts\JWTSubject;       // forces us to add the two JWT methods at the bottom

class User extends Authenticatable implements JWTSubject, FilamentUser
{
    /** @use HasFactory<\Database\Factories\UserFactory> */
    use HasFactory, Notifiable, HasRoles;

    /**
     * Tell spatie that this model's roles belong to the "api" guard
     * (because users log in through the JWT api guard, not the web guard).
     */
    protected $guard_name = 'api';

    /**
     * The attributes that are mass assignable (allowed in create()/update()).
     *
     * @var list<string>
     */
    protected $fillable = [
        'name',
        'email',
        'password',
        'phone',
        'role',
        'avatar',
        'fcm_token',
        'is_active',
        'lat',                 // rider's last known location (latitude)
        'lng',                 // rider's last known location (longitude)
        'location_updated_at', // when the location above was last updated
    ];

    /**
     * The attributes hidden when the model is turned into JSON.
     *
     * @var list<string>
     */
    protected $hidden = [
        'password',
        'remember_token',
    ];

    /**
     * Extra attribute(s) added to the JSON output.
     *
     * @var list<string>
     */
    protected $appends = [
        'avatar_url',
    ];

    /**
     * Automatic type conversions for attributes.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',     // any value set on "password" is automatically bcrypt-hashed
            'is_active' => 'boolean',
            'lat' => 'float',
            'lng' => 'float',
            'location_updated_at' => 'datetime',
        ];
    }

    /**
     * Turn the stored avatar path into a full, usable URL for the apps.
     * (Run `php artisan storage:link` once so /storage points to the files.)
     */
    public function getAvatarUrlAttribute(): ?string
    {
        return $this->avatar ? asset('storage/' . $this->avatar) : null;
    }

    /**
     * Decide which Filament web panel this user may sign in to.
     *
     * Login itself uses session auth (the "web" guard), but role checks below
     * resolve against the "api" guard because $guard_name is fixed to 'api'
     * on this model — so the same spatie roles used by the API gate the panels.
     * An inactive account is locked out of every panel.
     */
    public function canAccessPanel(Panel $panel): bool
    {
        if (! $this->is_active) {
            return false;
        }

        return match ($panel->getId()) {
            'admin'  => $this->hasRole('admin'),
            'vendor' => $this->hasRole('restaurant_owner'),
            default  => false,
        };
    }

    /**
     * A restaurant_owner has one restaurant (used from Phase 2 onwards).
     */
    public function restaurant(): HasOne
    {
        return $this->hasOne(Restaurant::class);
    }

    /**
     * Orders this user placed as a customer.
     */
    public function orders(): HasMany
    {
        return $this->hasMany(Order::class, 'customer_id');
    }

    /**
     * Orders this user is delivering as a rider.
     */
    public function deliveries(): HasMany
    {
        return $this->hasMany(Order::class, 'rider_id');
    }

    /**
     * Payouts made to this user as a rider.
     */
    public function payouts(): MorphMany
    {
        return $this->morphMany(\App\Models\Payout::class, 'payee');
    }

    /*
    |--------------------------------------------------------------------------
    | Methods required by the JWTSubject interface
    |--------------------------------------------------------------------------
    */

    /**
     * The value stored in the token's "sub" claim — here, the user id.
     */
    public function getJWTIdentifier()
    {
        return $this->getKey();
    }

    /**
     * Extra data baked into every token. Keep this small.
     *
     * @return array<string, mixed>
     */
    public function getJWTCustomClaims(): array
    {
        return [
            'role' => $this->role,
        ];
    }
}
