<?php

namespace App\Enums;

/**
 * Every possible state an order can be in.
 *
 * Normal flow:
 *   pending → accepted → preparing → ready_for_pickup → picked_up → delivered
 * An order may also be:
 *   cancelled
 *
 * Using a PHP "enum" gives us type-safety: instead of loose strings like
 * 'pending' we use OrderStatus::PENDING, and typos become errors.
 */
enum OrderStatus: string
{
    case PENDING          = 'pending';
    case ACCEPTED         = 'accepted';
    case PREPARING        = 'preparing';
    case READY_FOR_PICKUP = 'ready_for_pickup';
    case PICKED_UP        = 'picked_up';
    case DELIVERED        = 'delivered';
    case CANCELLED        = 'cancelled';

    /**
     * All status values as a plain array of strings.
     * Handy for the migration's enum column and for validation rules.
     *
     * @return array<int, string>
     */
    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }

    /**
     * The statuses an order is allowed to move to from THIS status.
     * This is what stops the flow from skipping steps.
     *
     * @return array<int, self>
     */
    public function allowedTransitions(): array
    {
        return match ($this) {
            self::PENDING          => [self::ACCEPTED, self::CANCELLED],
            self::ACCEPTED         => [self::PREPARING, self::CANCELLED],
            self::PREPARING        => [self::READY_FOR_PICKUP, self::CANCELLED],
            self::READY_FOR_PICKUP => [self::PICKED_UP, self::CANCELLED],
            self::PICKED_UP        => [self::DELIVERED],
            self::DELIVERED        => [], // final state
            self::CANCELLED        => [], // final state
        };
    }

    /**
     * Can an order in this status move to $next?
     */
    public function canTransitionTo(self $next): bool
    {
        return in_array($next, $this->allowedTransitions(), true);
    }
}
