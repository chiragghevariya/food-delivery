<?php

namespace App\Filament\Vendor\Resources\OrderResource\Pages;

use App\Enums\OrderStatus;
use App\Filament\Vendor\Resources\OrderResource;
use Filament\Resources\Components\Tab;
use Filament\Resources\Pages\ListRecords;
use Illuminate\Database\Eloquent\Builder;

class ListOrders extends ListRecords
{
    protected static string $resource = OrderResource::class;

    protected function getHeaderActions(): array
    {
        return []; // orders are not created here
    }

    /**
     * Group the queue into the stages an owner cares about.
     * The "New" tab shows a live count badge of orders awaiting acceptance.
     */
    public function getTabs(): array
    {
        return [
            'all' => Tab::make('All'),

            'new' => Tab::make('New')
                ->modifyQueryUsing(fn (Builder $query) => $query->where('status', OrderStatus::PENDING))
                ->badge(OrderResource::getEloquentQuery()->where('status', OrderStatus::PENDING)->count())
                ->badgeColor('danger'),

            'active' => Tab::make('In progress')
                ->modifyQueryUsing(fn (Builder $query) => $query->whereIn('status', [
                    OrderStatus::ACCEPTED, OrderStatus::PREPARING, OrderStatus::READY_FOR_PICKUP,
                ])),

            'on_the_way' => Tab::make('On the way')
                ->modifyQueryUsing(fn (Builder $query) => $query->where('status', OrderStatus::PICKED_UP)),

            'completed' => Tab::make('Completed')
                ->modifyQueryUsing(fn (Builder $query) => $query->whereIn('status', [
                    OrderStatus::DELIVERED, OrderStatus::CANCELLED,
                ])),
        ];
    }
}
