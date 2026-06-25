<?php

namespace App\Enums;

/**
 * Moderation state of a restaurant on the platform.
 *
 *   pending  → just signed up, waiting for a super-admin to review
 *   approved → live and visible to customers
 *   rejected → application declined / de-listed
 *
 * (This is separate from `is_open`, which is the owner toggling whether they
 * are accepting orders right now.)
 */
enum RestaurantStatus: string
{
    case PENDING  = 'pending';
    case APPROVED = 'approved';
    case REJECTED = 'rejected';

    /** All values as plain strings (for the migration enum + validation). */
    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }

    /** A short human-friendly label for the UI. */
    public function label(): string
    {
        return ucfirst($this->value);
    }
}
