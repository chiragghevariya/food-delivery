<?php

namespace App\Filament\Vendor\Resources\MenuItemResource\Pages;

use App\Filament\Vendor\Resources\MenuItemResource;
use Filament\Resources\Pages\CreateRecord;

class CreateMenuItem extends CreateRecord
{
    protected static string $resource = MenuItemResource::class;

    /**
     * Stamp the owner's restaurant_id server-side. The value is taken from the
     * authenticated user, never from form input, so an owner can never create
     * an item under another restaurant.
     */
    protected function mutateFormDataBeforeCreate(array $data): array
    {
        $restaurant = auth()->user()?->restaurant;

        abort_if($restaurant === null, 403, 'You do not have a restaurant yet.');

        $data['restaurant_id'] = $restaurant->id;

        return $data;
    }
}
