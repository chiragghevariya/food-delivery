<?php

namespace App\Models;

use App\Enums\PayoutStatus;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphTo;

/**
 * A payout owed to a restaurant or a rider for a period of orders.
 */
class Payout extends Model
{
    protected $fillable = [
        'payee_type',
        'payee_id',
        'period_start',
        'period_end',
        'orders_count',
        'gross_amount',
        'commission_amount',
        'net_amount',
        'status',
        'paid_at',
        'reference',
        'notes',
    ];

    protected function casts(): array
    {
        return [
            'status'            => PayoutStatus::class,
            'period_start'      => 'date',
            'period_end'        => 'date',
            'orders_count'      => 'integer',
            'gross_amount'      => 'decimal:2',
            'commission_amount' => 'decimal:2',
            'net_amount'        => 'decimal:2',
            'paid_at'           => 'datetime',
        ];
    }

    /** The restaurant or rider being paid. */
    public function payee(): MorphTo
    {
        return $this->morphTo();
    }

    /** Mark this payout as settled. */
    public function markPaid(?string $reference = null): void
    {
        $this->update([
            'status'    => PayoutStatus::PAID,
            'paid_at'   => now(),
            'reference' => $reference ?? $this->reference,
        ]);
    }
}
