<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class OrderItem extends Model
{
    use HasFactory;

    /**
     * Columns that may be mass assigned.
     *
     * @var list<string>
     */
    protected $fillable = [
        'order_id',
        'menu_item_id',
        'quantity',
        'unit_price',
        'subtotal',
    ];

    /**
     * Automatic type conversions.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'quantity'   => 'integer',
            'unit_price' => 'decimal:2',
            'subtotal'   => 'decimal:2',
        ];
    }

    /** The order this line belongs to. */
    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }

    /** The menu item that was ordered (may be null if it was later deleted). */
    public function menuItem(): BelongsTo
    {
        return $this->belongsTo(MenuItem::class);
    }
}
