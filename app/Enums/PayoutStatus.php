<?php

namespace App\Enums;

/**
 * Lifecycle of a payout record (money owed to a restaurant or a rider).
 *
 *   pending    → calculated, not yet sent
 *   processing → marked for payment / in transit
 *   paid       → settled
 *   failed     → payment attempt failed, needs retry
 */
enum PayoutStatus: string
{
    case PENDING    = 'pending';
    case PROCESSING = 'processing';
    case PAID       = 'paid';
    case FAILED     = 'failed';

    /** All values as plain strings (for the migration enum + validation). */
    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }

    public function label(): string
    {
        return ucfirst($this->value);
    }
}
