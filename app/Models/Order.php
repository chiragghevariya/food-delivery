<?php

namespace App\Models;

use App\Enums\OrderStatus;
use App\Observers\OrderObserver;
use Illuminate\Database\Eloquent\Attributes\ObservedBy;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Validation\ValidationException;

#[ObservedBy(OrderObserver::class)]
class Order extends Model
{
    use HasFactory;

    /**
     * Columns that may be mass assigned.
     *
     * @var list<string>
     */
    protected $fillable = [
        'customer_id',
        'restaurant_id',
        'rider_id',
        'status',
        'subtotal',
        'delivery_fee',
        'total',
        'address',
        'lat',
        'lng',
        'notes',
        'payment_intent_id',
        'restaurant_payout_id',
        'rider_payout_id',
    ];

    /**
     * Automatic type conversions.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'status'       => OrderStatus::class, // string in DB <-> OrderStatus enum in code
            'subtotal'     => 'decimal:2',
            'delivery_fee' => 'decimal:2',
            'total'        => 'decimal:2',
            'lat'          => 'float',
            'lng'          => 'float',
        ];
    }

    /*
    |--------------------------------------------------------------------------
    | Relationships
    |--------------------------------------------------------------------------
    */

    /** The customer who placed the order. */
    public function customer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'customer_id');
    }

    /** The restaurant the order was placed at. */
    public function restaurant(): BelongsTo
    {
        return $this->belongsTo(Restaurant::class);
    }

    /** The rider delivering the order (may be null). */
    public function rider(): BelongsTo
    {
        return $this->belongsTo(User::class, 'rider_id');
    }

    /** The product lines in this order. */
    public function items(): HasMany
    {
        return $this->hasMany(OrderItem::class);
    }

    /** The status timeline (oldest first). */
    public function statusHistory(): HasMany
    {
        return $this->hasMany(OrderStatusHistory::class)->orderBy('created_at');
    }

    /*
    |--------------------------------------------------------------------------
    | Helpers
    |--------------------------------------------------------------------------
    */

    /**
     * Change the order status AND record it in the history log in one step.
     * Reuse this anywhere an order's status changes (rider/restaurant in later phases).
     */
    public function changeStatus(OrderStatus $status, ?string $note = null): void
    {
        $this->update(['status' => $status]);

        $this->statusHistory()->create([
            'status' => $status->value,
            'note'   => $note,
        ]);
    }

    /**
     * Move the order to a new status, but ONLY if the flow allows it
     * (no skipping steps). Throws a 422 validation error if not allowed.
     * This is the method status changes should normally go through.
     */
    public function transitionTo(OrderStatus $to, ?string $note = null): void
    {
        if (! $this->status->canTransitionTo($to)) {
            throw ValidationException::withMessages([
                'status' => "Cannot change order status from '{$this->status->value}' to '{$to->value}'.",
            ]);
        }

        $this->changeStatus($to, $note);
    }
}
