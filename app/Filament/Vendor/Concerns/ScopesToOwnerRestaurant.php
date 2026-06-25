<?php

namespace App\Filament\Vendor\Concerns;

use App\Models\Restaurant;
use Illuminate\Database\Eloquent\Builder;

/**
 * Restricts a vendor-panel resource to the signed-in owner's own restaurant.
 *
 * Applied to any resource whose model has a `restaurant_id` column
 * (MenuItem, Order). The query is filtered to the owner's restaurant id;
 * if the owner somehow has no restaurant yet, id 0 matches nothing, so the
 * resource is simply empty rather than leaking another restaurant's data.
 */
trait ScopesToOwnerRestaurant
{
    /** The restaurant belonging to the currently signed-in owner (cached per request). */
    protected static function ownerRestaurant(): ?Restaurant
    {
        return once(fn () => auth()->user()?->restaurant);
    }

    /** Convenience: the owner's restaurant id, or 0 when none exists. */
    protected static function ownerRestaurantId(): int
    {
        return static::ownerRestaurant()?->id ?? 0;
    }

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()
            ->where(static::getModel()::make()->getTable() . '.restaurant_id', static::ownerRestaurantId());
    }
}
