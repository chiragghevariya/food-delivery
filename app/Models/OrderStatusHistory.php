<?php

namespace App\Models;

use App\Enums\OrderStatus;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class OrderStatusHistory extends Model
{
    // The table name is singular ("...history"), so we set it manually
    // (otherwise Eloquent would look for "order_status_histories").
    protected $table = 'order_status_history';

    // This log only has a created_at column — tell Eloquent there is no updated_at.
    const UPDATED_AT = null;

    /**
     * Columns that may be mass assigned.
     *
     * @var list<string>
     */
    protected $fillable = [
        'order_id',
        'status',
        'note',
    ];

    /**
     * Automatic type conversions.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'status' => OrderStatus::class,
        ];
    }

    /** The order this history entry belongs to. */
    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }
}
